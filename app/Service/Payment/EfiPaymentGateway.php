<?php

namespace App\Service\Payment;

use App\Contracts\PaymentChargeResult;
use App\Contracts\PaymentGatewayContract;
use App\Contracts\WebhookResult;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * EFI (Efí Bank / Gerencianet) payment gateway.
 *
 * Supports three payment methods, which live on TWO distinct EFI APIs:
 *
 *   - Pix    : BACEN Pix API (/v2/cob/{txid}). Host pix.api.efipay.com.br,
 *              OAuth2 client_credentials with HTTP Basic auth OVER mTLS
 *              (the .pem generated from the account .p12).
 *   - Boleto : Cobranças API (/v1/charge + one-step). Host
 *              cobrancas.api.efipay.com.br, OAuth2 Basic WITHOUT mTLS.
 *   - Card   : Cobranças API (/v1/charge/one-step with a payment_token). Also
 *              on cobrancas.api.efipay.com.br, no mTLS. Credit card charges are
 *              authorized SYNCHRONOUSLY: the final status ("approved") is
 *              returned right after createCharge.
 *
 * The payment_token for card charges must be generated in the frontend with
 * the Efí JS SDK — raw card data never reaches this backend (PCI compliance).
 */
class EfiPaymentGateway implements PaymentGatewayContract
{
    private const PIX_TOKEN_CACHE_KEY = 'efi_pix_access_token';
    private const COBRANCAS_TOKEN_CACHE_KEY = 'efi_cobrancas_access_token';

    public function __construct(
        private readonly string $pixBaseUrl,
        private readonly string $cobrancasBaseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $pixKey,
        private readonly string $certificatePath,
        private readonly ?string $privateKeyPath = null,
        private readonly int $cobExpiration = 3600,
        private readonly int $boletoDueDays = 3,
    ) {}

    public function createCharge(
        int $amountCents,
        string $userRef,
        string $returnUrl,
        PaymentMethod $method = PaymentMethod::Pix,
        array $payer = [],
    ): PaymentChargeResult {
        return match ($method) {
            PaymentMethod::Boleto => $this->createBoleto($amountCents, $userRef, $payer),
            PaymentMethod::Card => $this->createCardCharge($amountCents, $userRef, $payer),
            default => $this->createPix($amountCents, $userRef, $returnUrl),
        };
    }

    public function checkStatus(string $chargeId): PaymentStatus
    {
        if (str_starts_with($chargeId, 'boleto:')) {
            return $this->checkCobrancaStatus(substr($chargeId, 7));
        }

        if (str_starts_with($chargeId, 'card:')) {
            return $this->checkCobrancaStatus(substr($chargeId, 5));
        }

        $response = $this->pixClient()
            ->get("/v2/cob/{$chargeId}");

        if ($response->failed()) {
            Log::warning('EFI Pix checkStatus failed', [
                'txid' => $chargeId,
                'status' => $response->status(),
            ]);
            return PaymentStatus::Pending;
        }

        return $this->mapPixStatus($response->json('status'));
    }

    public function handleWebhook(array $payload): WebhookResult
    {
        // Cobranças (boleto/card) webhook. EFI posts the charge notification
        // token; the integrator then fetches the charge detail. We accept the
        // common shapes: a direct charge_id/status or the notification list.
        $cobrancaChargeId = $payload['identificadores']['charge_id']
            ?? $payload['charge_id']
            ?? $payload['codigoSolicitacao']
            ?? null;

        if ($cobrancaChargeId !== null) {
            $situacao = $payload['status']['current']
                ?? $payload['status']
                ?? $payload['situacao']
                ?? null;

            // Preserve the method prefix so settlement keys match the stored
            // gateway_reference. Default to boleto: prefix for unknown method.
            $prefix = ($payload['payment'] ?? null) === 'credit_card' ? 'card:' : 'boleto:';

            return new WebhookResult(
                chargeId: $prefix . $cobrancaChargeId,
                status: $this->mapCobrancaStatus(is_array($situacao) ? ($situacao['current'] ?? null) : $situacao),
            );
        }

        // Pix webhook: { "pix": [ { "txid": "...", ... } ] }
        $pix = $payload['pix'][0] ?? null;

        if (!$pix || empty($pix['txid'])) {
            return new WebhookResult(
                chargeId: 'unknown',
                status: PaymentStatus::Pending,
                errorMessage: 'Payload de webhook inválido.',
            );
        }

        return new WebhookResult(
            chargeId: $pix['txid'],
            status: PaymentStatus::Completed,
        );
    }

    // ----------------------------------------------------------------- Pix

    private function createPix(int $amountCents, string $userRef, string $returnUrl): PaymentChargeResult
    {
        $txid = $this->generateTxid();

        $payload = [
            'calendario' => ['expiracao' => $this->cobExpiration],
            'valor' => ['original' => $this->centsToDecimalString($amountCents)],
            'chave' => $this->pixKey,
            'solicitacaoPagador' => 'Pagamento SisBrasFute',
            'infoAdicionais' => [
                ['nome' => 'userRef', 'valor' => $userRef],
            ],
        ];

        $response = $this->pixClient()
            ->put("/v2/cob/{$txid}", $payload);

        if ($response->failed()) {
            Log::error('EFI Pix createCharge failed', [
                'txid' => $txid,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Falha ao criar cobrança Pix na EFI.');
        }

        $data = $response->json();
        $resolvedTxid = $data['txid'] ?? $txid;

        $qrCode = $this->fetchPixQrCode($data['loc']['id'] ?? null);

        return new PaymentChargeResult(
            chargeId: $resolvedTxid,
            paymentUrl: $returnUrl,
            status: PaymentStatus::Pending->value,
            method: PaymentMethod::Pix->value,
            pixCopiaECola: $qrCode['pixCopiaECola'] ?? ($data['pixCopiaECola'] ?? null),
            pixQrCodeBase64: $qrCode['imagemQrcode'] ?? null,
        );
    }

    /**
     * Resolves the Pix "copia e cola" and base64 QR Code image from the
     * location (loc) generated for the charge.
     *
     * @return array<string, mixed>
     */
    private function fetchPixQrCode(?int $locId): array
    {
        if (!$locId) {
            return [];
        }

        $response = $this->pixClient()->get("/v2/loc/{$locId}/qrcode");

        if ($response->failed()) {
            Log::warning('EFI Pix QrCode fetch failed', [
                'locId' => $locId,
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    private function mapPixStatus(?string $efiStatus): PaymentStatus
    {
        return match ($efiStatus) {
            'CONCLUIDA' => PaymentStatus::Completed,
            'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    // -------------------------------------------------------------- Boleto

    private function createBoleto(int $amountCents, string $userRef, array $payer): PaymentChargeResult
    {
        throw_if(
            empty($payer['name']) || empty($payer['document']),
            new \InvalidArgumentException('Nome e CPF/CNPJ do pagador são obrigatórios para boleto.')
        );

        $payload = [
            'items' => $this->buildItems($amountCents),
            'payment' => [
                'banking_billet' => [
                    'expire_at' => now()->addDays($this->boletoDueDays)->format('Y-m-d'),
                    'customer' => $this->buildCustomer($payer),
                ],
            ],
        ];

        $response = $this->cobrancasClient()
            ->post('/v1/charge/one-step', $payload);

        if ($response->failed()) {
            Log::error('EFI Boleto createCharge failed', [
                'userRef' => $userRef,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Falha ao emitir boleto na EFI.');
        }

        $data = $response->json('data') ?? $response->json();
        $chargeId = (string) ($data['charge_id'] ?? $data['id'] ?? '');

        return new PaymentChargeResult(
            chargeId: 'boleto:' . $chargeId,
            paymentUrl: $data['link'] ?? ($data['pdf']['charge'] ?? ''),
            status: PaymentStatus::Pending->value,
            method: PaymentMethod::Boleto->value,
            boletoLinhaDigitavel: $data['barcode'] ?? null,
            boletoPdfUrl: $data['pdf']['charge'] ?? null,
            boletoDueDate: $data['expire_at'] ?? now()->addDays($this->boletoDueDays)->format('Y-m-d'),
        );
    }

    // ---------------------------------------------------------- Credit card

    /**
     * Creates a one-step credit card charge. The card itself is represented by
     * a payment_token generated in the frontend with the Efí JS SDK. Card
     * charges are authorized synchronously, so the returned status reflects the
     * acquirer's decision (approved / unauthorized) immediately.
     *
     * Expected $payer keys: name, document, email (required by EFI for card),
     * payment_token (required), installments (optional, default 1), plus
     * optional phone, birth and billing_address fields.
     */
    private function createCardCharge(int $amountCents, string $userRef, array $payer): PaymentChargeResult
    {
        throw_if(
            empty($payer['payment_token']),
            new \InvalidArgumentException('payment_token é obrigatório para pagamento com cartão.')
        );
        throw_if(
            empty($payer['name']) || empty($payer['document']) || empty($payer['email']),
            new \InvalidArgumentException('Nome, CPF/CNPJ e e-mail do pagador são obrigatórios para cartão.')
        );

        $installments = max(1, (int) ($payer['installments'] ?? 1));

        $creditCard = [
            'customer' => $this->buildCustomer($payer),
            'installments' => $installments,
            'payment_token' => $payer['payment_token'],
        ];

        if (!empty($payer['billing_address'])) {
            $creditCard['billing_address'] = $this->buildAddress($payer['billing_address']);
        }

        $payload = [
            'items' => $this->buildItems($amountCents),
            'payment' => [
                'credit_card' => $creditCard,
            ],
        ];

        $response = $this->cobrancasClient()
            ->post('/v1/charge/one-step', $payload);

        if ($response->failed()) {
            Log::error('EFI Card createCharge failed', [
                'userRef' => $userRef,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Falha ao processar pagamento com cartão na EFI.');
        }

        $data = $response->json('data') ?? $response->json();
        $chargeId = (string) ($data['charge_id'] ?? $data['id'] ?? '');
        $status = $this->mapCardStatus($data['status'] ?? null);

        // A rejected card is a terminal failure right away.
        throw_if(
            $status === PaymentStatus::Failed,
            new \RuntimeException($data['refusal']['reason'] ?? 'Pagamento com cartão não autorizado.')
        );

        $installmentValue = isset($data['installment_value'])
            ? (int) $data['installment_value']
            : null;

        return new PaymentChargeResult(
            chargeId: 'card:' . $chargeId,
            paymentUrl: '',
            status: $status->value,
            method: PaymentMethod::Card->value,
            installments: (int) ($data['installments'] ?? $installments),
            installmentValueCents: $installmentValue,
        );
    }

    private function mapCardStatus(?string $status): PaymentStatus
    {
        return match ($status) {
            'approved', 'paid', 'settled' => PaymentStatus::Completed,
            'unauthorized', 'refunded', 'contested', 'canceled' => PaymentStatus::Failed,
            'expired' => PaymentStatus::Expired,
            // 'new', 'waiting', 'identified', 'link' remain pending.
            default => PaymentStatus::Pending,
        };
    }

    // -------------------------------------------------- Cobranças (shared)

    private function checkCobrancaStatus(string $chargeId): PaymentStatus
    {
        $response = $this->cobrancasClient()
            ->get("/v1/charge/{$chargeId}");

        if ($response->failed()) {
            return PaymentStatus::Pending;
        }

        $data = $response->json('data') ?? $response->json();

        return $this->mapCobrancaStatus($data['status'] ?? null);
    }

    private function mapCobrancaStatus(?string $situacao): PaymentStatus
    {
        return match ($situacao) {
            'paid', 'settled', 'approved', 'RECEBIDO', 'MARCADO_RECEBIDO' => PaymentStatus::Completed,
            'canceled', 'unpaid', 'refunded', 'contested', 'unauthorized', 'CANCELADO', 'FALHA_EMISSAO' => PaymentStatus::Failed,
            'expired', 'EXPIRADO' => PaymentStatus::Expired,
            // 'new', 'waiting', 'identified', 'link' stay pending.
            default => PaymentStatus::Pending,
        };
    }

    /**
     * Builds the single-item array charged for a given amount in centavos. EFI
     * Cobranças expects item "value" in centavos and an integer "amount".
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(int $amountCents): array
    {
        return [[
            'name' => 'Pagamento SisBrasFute',
            'value' => $amountCents,
            'amount' => 1,
        ]];
    }

    /**
     * Builds the EFI customer object from the payer array, choosing between
     * natural (cpf) and legal (juridical_person) person based on the document.
     *
     * @param array<string, mixed> $payer
     * @return array<string, mixed>
     */
    private function buildCustomer(array $payer): array
    {
        $document = preg_replace('/\D/', '', (string) $payer['document']);
        $isLegalPerson = strlen($document) > 11;

        $customer = array_filter([
            'name' => $payer['name'],
            'email' => $payer['email'] ?? null,
            'phone_number' => preg_replace('/\D/', '', (string) ($payer['phone'] ?? '')) ?: null,
            'birth' => $payer['birth'] ?? null,
        ], fn ($v) => $v !== null);

        if ($isLegalPerson) {
            $customer['juridical_person'] = [
                'corporate_name' => $payer['corporate_name'] ?? $payer['name'],
                'cnpj' => $document,
            ];
        } else {
            $customer['cpf'] = $document;
        }

        if (!empty($payer['address'])) {
            $customer['address'] = $this->buildAddress([
                'street' => $payer['address'],
                'number' => $payer['number'] ?? 'S/N',
                'neighborhood' => $payer['neighborhood'] ?? null,
                'zipcode' => $payer['cep'] ?? null,
                'city' => $payer['city'] ?? null,
                'state' => $payer['uf'] ?? null,
                'complement' => $payer['complement'] ?? null,
            ]);
        }

        return $customer;
    }

    /**
     * Normalizes an address array into EFI's expected shape.
     *
     * @param array<string, mixed> $address
     * @return array<string, mixed>
     */
    private function buildAddress(array $address): array
    {
        $zipcode = isset($address['zipcode']) || isset($address['cep'])
            ? preg_replace('/\D/', '', (string) ($address['zipcode'] ?? $address['cep']))
            : null;

        return array_filter([
            'street' => $address['street'] ?? null,
            'number' => $address['number'] ?? null,
            'neighborhood' => $address['neighborhood'] ?? null,
            'zipcode' => $zipcode,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? $address['uf'] ?? null,
            'complement' => $address['complement'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    // ----------------------------------------------------------- Internals

    /**
     * HTTP client for the Pix API (mTLS required).
     */
    private function pixClient(): PendingRequest
    {
        $request = Http::baseUrl($this->pixBaseUrl)
            ->withToken($this->getPixAccessToken())
            ->acceptJson()
            ->asJson();

        return $this->withCertificate($request);
    }

    /**
     * HTTP client for the Cobranças API (boleto + card, no mTLS).
     */
    private function cobrancasClient(): PendingRequest
    {
        return Http::baseUrl($this->cobrancasBaseUrl)
            ->withToken($this->getCobrancasAccessToken())
            ->acceptJson()
            ->asJson();
    }

    private function getPixAccessToken(): string
    {
        return Cache::remember(self::PIX_TOKEN_CACHE_KEY, now()->addMinutes(50), function () {
            $request = Http::baseUrl($this->pixBaseUrl)
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->asJson();

            $response = $this->withCertificate($request)
                ->post('/oauth/token', ['grant_type' => 'client_credentials']);

            if ($response->failed()) {
                Log::error('EFI Pix token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Falha na autenticação Pix com a EFI.');
            }

            return $response->json('access_token');
        });
    }

    private function getCobrancasAccessToken(): string
    {
        return Cache::remember(self::COBRANCAS_TOKEN_CACHE_KEY, now()->addMinutes(50), function () {
            $response = Http::baseUrl($this->cobrancasBaseUrl)
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->asJson()
                ->post('/v1/authorize', ['grant_type' => 'client_credentials']);

            if ($response->failed()) {
                Log::error('EFI Cobranças token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Falha na autenticação Cobranças com a EFI.');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Applies the mTLS client certificate to a Pix request. EFI issues a single
     * .pem that bundles the certificate and private key; when a separate key
     * path is configured it is passed as ssl_key.
     */
    private function withCertificate(PendingRequest $request): PendingRequest
    {
        $options = ['cert' => $this->certificatePath];

        if (!empty($this->privateKeyPath)) {
            $options['ssl_key'] = $this->privateKeyPath;
        }

        return $request->withOptions($options);
    }

    private function centsToDecimalString(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }

    private function generateTxid(): string
    {
        // EFI/BACEN txid: 26-35 chars, alphanumeric only.
        return Str::lower(Str::random(32));
    }
}
