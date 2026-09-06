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
 * Banco Inter (PJ) payment gateway.
 *
 * Supports two payment methods:
 *   - Pix    : standard BACEN Pix API (/pix/v2/cob).
 *   - Boleto : Inter Cobrança API v3 (/cobranca/v3/cobrancas).
 *
 * Authentication uses OAuth2 client_credentials over mTLS. The certificate
 * and key are downloaded from the Inter PJ internet banking
 * (Gestão de Integrações).
 */
class InterPaymentGateway implements PaymentGatewayContract
{
    private const TOKEN_CACHE_KEY = 'inter_access_token';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $pixKey,
        private readonly string $certificatePath,
        private readonly string $privateKeyPath,
        private readonly int $cobExpiration = 3600,
        private readonly ?string $contaCorrente = null,
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
            default => $this->createPix($amountCents, $userRef, $returnUrl),
        };
    }

    public function checkStatus(string $chargeId): PaymentStatus
    {
        // chargeId prefixed to know which API to query.
        if (str_starts_with($chargeId, 'boleto:')) {
            return $this->checkBoletoStatus(substr($chargeId, 7));
        }

        $response = $this->client(['cob.read'])
            ->get("/pix/v2/cob/{$chargeId}");

        if ($response->failed()) {
            Log::warning('Inter Pix checkStatus failed', [
                'txid' => $chargeId,
                'status' => $response->status(),
            ]);
            return PaymentStatus::Pending;
        }

        return $this->mapPixStatus($response->json('status'));
    }

    public function handleWebhook(array $payload): WebhookResult
    {
        // Boleto (Cobrança) webhook: { "codigoSolicitacao": "...", "situacao": "RECEBIDO" }
        if (isset($payload['codigoSolicitacao'])) {
            $situacao = $payload['situacao'] ?? null;
            return new WebhookResult(
                chargeId: 'boleto:' . $payload['codigoSolicitacao'],
                status: $this->mapBoletoStatus($situacao),
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

        $response = $this->client(['cob.write', 'cob.read'])
            ->put("/pix/v2/cob/{$txid}", $payload);

        if ($response->failed()) {
            Log::error('Inter Pix createCharge failed', [
                'txid' => $txid,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Falha ao criar cobrança Pix no Banco Inter.');
        }

        $data = $response->json();

        return new PaymentChargeResult(
            chargeId: $data['txid'] ?? $txid,
            paymentUrl: $returnUrl,
            status: PaymentStatus::Pending->value,
            method: PaymentMethod::Pix->value,
            pixCopiaECola: $data['pixCopiaECola'] ?? null,
            pixQrCodeBase64: $data['loc']['qrcode'] ?? null,
        );
    }

    private function mapPixStatus(?string $interStatus): PaymentStatus
    {
        return match ($interStatus) {
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

        $seuNumero = substr('SBF' . $userRef . Str::upper(Str::random(6)), 0, 15);
        $document = preg_replace('/\D/', '', (string) $payer['document']);
        $personType = strlen($document) > 11 ? 'JURIDICA' : 'FISICA';

        $payload = [
            'seuNumero' => $seuNumero,
            'valorNominal' => round($amountCents / 100, 2),
            'dataVencimento' => now()->addDays($this->boletoDueDays)->format('Y-m-d'),
            'numDiasAgenda' => 30,
            'pagador' => [
                'cpfCnpj' => $document,
                'tipoPessoa' => $personType,
                'nome' => $payer['name'],
                'endereco' => $payer['address'] ?? 'Não informado',
                'cidade' => $payer['city'] ?? 'Não informado',
                'uf' => $payer['uf'] ?? 'SP',
                'cep' => preg_replace('/\D/', '', (string) ($payer['cep'] ?? '00000000')),
            ],
        ];

        $response = $this->client(['boleto-cobranca.write', 'boleto-cobranca.read'])
            ->post('/cobranca/v3/cobrancas', $payload);

        if ($response->failed()) {
            Log::error('Inter Boleto createCharge failed', [
                'seuNumero' => $seuNumero,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Falha ao emitir boleto no Banco Inter.');
        }

        $codigoSolicitacao = $response->json('codigoSolicitacao');
        $detail = $this->fetchBoletoDetail($codigoSolicitacao);

        return new PaymentChargeResult(
            chargeId: 'boleto:' . $codigoSolicitacao,
            paymentUrl: '',
            status: PaymentStatus::Pending->value,
            method: PaymentMethod::Boleto->value,
            boletoLinhaDigitavel: $detail['linhaDigitavel'] ?? null,
            boletoPdfUrl: null,
            boletoDueDate: $payload['dataVencimento'],
        );
    }

    /**
     * Retrieves the issued boleto detail (barcode / digitable line).
     *
     * @return array<string, mixed>
     */
    private function fetchBoletoDetail(string $codigoSolicitacao): array
    {
        $response = $this->client(['boleto-cobranca.read'])
            ->get("/cobranca/v3/cobrancas/{$codigoSolicitacao}");

        if ($response->failed()) {
            Log::warning('Inter Boleto detail fetch failed', [
                'codigoSolicitacao' => $codigoSolicitacao,
                'status' => $response->status(),
            ]);
            return [];
        }

        $data = $response->json();

        // The v3 response nests boleto data under "boleto".
        return $data['boleto'] ?? $data;
    }

    private function checkBoletoStatus(string $codigoSolicitacao): PaymentStatus
    {
        $response = $this->client(['boleto-cobranca.read'])
            ->get("/cobranca/v3/cobrancas/{$codigoSolicitacao}");

        if ($response->failed()) {
            return PaymentStatus::Pending;
        }

        $situacao = $response->json('cobranca.situacao') ?? $response->json('situacao');

        return $this->mapBoletoStatus($situacao);
    }

    private function mapBoletoStatus(?string $situacao): PaymentStatus
    {
        return match ($situacao) {
            'RECEBIDO', 'MARCADO_RECEBIDO' => PaymentStatus::Completed,
            'CANCELADO', 'EXPIRADO', 'FALHA_EMISSAO' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    // ----------------------------------------------------------- Internals

    /**
     * @param array<int, string> $scopes
     */
    private function client(array $scopes): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)
            ->withToken($this->getAccessToken($scopes))
            ->withOptions([
                'cert' => $this->certificatePath,
                'ssl_key' => $this->privateKeyPath,
            ])
            ->acceptJson()
            ->asJson();

        if ($this->contaCorrente) {
            $request = $request->withHeaders(['x-conta-corrente' => $this->contaCorrente]);
        }

        return $request;
    }

    /**
     * @param array<int, string> $scopes
     */
    private function getAccessToken(array $scopes): string
    {
        $scope = implode(' ', $scopes);
        $cacheKey = self::TOKEN_CACHE_KEY . ':' . md5($scope);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($scope) {
            $response = Http::baseUrl($this->baseUrl)
                ->asForm()
                ->withOptions([
                    'cert' => $this->certificatePath,
                    'ssl_key' => $this->privateKeyPath,
                ])
                ->post('/oauth/v2/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                    'scope' => $scope,
                ]);

            if ($response->failed()) {
                Log::error('Inter token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Falha na autenticação com o Banco Inter.');
            }

            return $response->json('access_token');
        });
    }

    private function centsToDecimalString(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }

    private function generateTxid(): string
    {
        return Str::lower(Str::random(32));
    }
}
