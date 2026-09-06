<?php

namespace App\Http\Controllers;

use App\Service\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
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
                'payment_method' => 'sometimes|in:pix,boleto',
                'payer_name' => 'required_if:payment_method,boleto|string',
                'payer_document' => 'required_if:payment_method,boleto|string',
                'payer_cep' => 'sometimes|nullable|string',
                'payer_address' => 'sometimes|nullable|string',
                'payer_city' => 'sometimes|nullable|string',
                'payer_uf' => 'sometimes|nullable|string|size:2',
            ]);

            $userId = auth()->id();
            $returnUrl = $request->input('return_url', config('app.frontend_url') . '/financeiro');
            $method = \App\Enums\PaymentMethod::from($request->input('payment_method', 'pix'));

            $payer = [
                'name' => $request->input('payer_name'),
                'document' => $request->input('payer_document'),
                'cep' => $request->input('payer_cep'),
                'address' => $request->input('payer_address'),
                'city' => $request->input('payer_city'),
                'uf' => $request->input('payer_uf'),
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
                    'payment_url' => $chargeResult->paymentUrl,
                    'pix_copia_e_cola' => $chargeResult->pixCopiaECola,
                    'pix_qrcode_base64' => $chargeResult->pixQrCodeBase64,
                    'boleto_linha_digitavel' => $chargeResult->boletoLinhaDigitavel,
                    'boleto_pdf_url' => $chargeResult->boletoPdfUrl,
                    'boleto_due_date' => $chargeResult->boletoDueDate,
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

    public function webhookCallback(Request $request): JsonResponse
    {
        try {
            $this->walletService->handleWebhook($request->all());

            return response()->json([], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }
}
