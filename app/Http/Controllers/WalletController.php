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
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function deposit(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'amount_cents' => 'required|integer|min:100',
            ]);

            $userId = auth()->id();
            $returnUrl = $request->input('return_url', config('app.url') . '/financeiro');

            $chargeResult = $this->walletService->initiateDeposit(
                $userId,
                $request->input('amount_cents'),
                $returnUrl
            );

            return response()->json(
                ['payment_url' => $chargeResult->paymentUrl],
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
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function webhookCallback(Request $request): JsonResponse
    {
        try {
            $this->walletService->handleDepositWebhook($request->all());

            return response()->json([], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
