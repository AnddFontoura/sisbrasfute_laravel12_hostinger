<?php

namespace App\Http\Controllers;

use App\Service\WalletService;
use App\Service\WebhookIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected WebhookIngestService $webhookIngestService,
    ) {

    }

    public function balance(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $balance = $this->walletService->getBalance($userId);

            return response()->json(
                ['balance_cents' => $balance],
                JsonResponse::HTTP_OK
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function deposit(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'amount_cents' => 'required|integer|min:100',
                'payment_method' => 'sometimes|in:pix,boleto,card',
                'payer_name' => 'required_if:payment_method,boleto,card|string',
                'payer_document' => 'required_if:payment_method,boleto,card|string',
                'payer_email' => 'required_if:payment_method,card|nullable|email',
                'payer_phone' => 'sometimes|nullable|string',
                'payer_cep' => 'sometimes|nullable|string',
                'payer_address' => 'sometimes|nullable|string',
                'payer_city' => 'sometimes|nullable|string',
                'payer_uf' => 'sometimes|nullable|string|size:2',
                // Credit card: payment_token comes from the Efí JS SDK (frontend).
                'payment_token' => 'required_if:payment_method,card|string',
                'installments' => 'sometimes|nullable|integer|min:1|max:12',
            ]);

            $userId = auth()->id();
            $returnUrl = $request->input('return_url', config('app.frontend_url') . '/financeiro');
            $method = \App\Enums\PaymentMethod::from($request->input('payment_method', 'pix'));

            $payer = [
                'name' => $request->input('payer_name'),
                'document' => $request->input('payer_document'),
                'email' => $request->input('payer_email'),
                'phone' => $request->input('payer_phone'),
                'cep' => $request->input('payer_cep'),
                'address' => $request->input('payer_address'),
                'city' => $request->input('payer_city'),
                'uf' => $request->input('payer_uf'),
                'payment_token' => $request->input('payment_token'),
                'installments' => $request->input('installments'),
            ];

            $chargeResult = $this->walletService->initiateDeposit(
                $userId,
                $request->input('amount_cents'),
                $returnUrl,
                $method,
                $payer,
            );

            return response()->json(
                [
                    'payment_method' => $chargeResult->method,
                    'payment_status' => $chargeResult->status,
                    'payment_url' => $chargeResult->paymentUrl,
                    'pix_copia_e_cola' => $chargeResult->pixCopiaECola,
                    'pix_qrcode_base64' => $chargeResult->pixQrCodeBase64,
                    'boleto_linha_digitavel' => $chargeResult->boletoLinhaDigitavel,
                    'boleto_pdf_url' => $chargeResult->boletoPdfUrl,
                    'boleto_due_date' => $chargeResult->boletoDueDate,
                    'installments' => $chargeResult->installments,
                    'installment_value_cents' => $chargeResult->installmentValueCents,
                ],
                JsonResponse::HTTP_CREATED
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(
                ['message' => $e->getMessage(), 'errors' => $e->errors()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    /**
     * Inbound gateway webhook (EFI/Inter Pix + boleto/card).
     *
     * This endpoint only buffers the event durably and enqueues it on RabbitMQ,
     * then acks the gateway with 200 immediately. Settlement happens on the
     * worker (ProcessPaymentWebhookJob) with retries and idempotency, so a slow
     * or failing settle never makes us lose a confirmed payment.
     *
     * We always return 200 once the event is safely recorded/enqueued: a 5xx
     * here would make the gateway retry needlessly, and the event is already
     * persisted for reconciliation.
     */
    public function webhookCallback(Request $request): JsonResponse
    {
        try {
            $this->webhookIngestService->ingest($request->all());
        } catch (\Throwable $e) {
            // Even if enqueueing/recording hiccups, don't 5xx the gateway into a
            // retry storm — log it; the reconciliation job is the safety net.
            Log::error('Webhook ingest failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([], JsonResponse::HTTP_OK);
    }
}
