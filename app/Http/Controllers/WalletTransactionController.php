<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use App\Service\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletTransactionController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
    ) {

    }

    public function index(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $query = WalletTransaction::where('user_id', $userId)
                ->orderBy('created_at', 'desc');

            if ($request->has('type') && $request->input('type')) {
                $query->where('type', $request->input('type'));
            }

            $perPage = $request->input('per_page', 15);
            $transactions = $query->paginate($perPage);

            $balance = $this->walletService->getBalance($userId);

            return response()->json([
                'data' => $transactions->items(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'balance_cents' => $balance,
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
