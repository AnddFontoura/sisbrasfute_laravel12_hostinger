<?php

namespace App\Http\Controllers;

use App\Models\SystemRevenue;
use App\Service\SystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    public function __construct(
        protected SystemConfigService $systemConfigService,
    ) {

    }

    public function getFee(): JsonResponse
    {
        try {
            return response()->json([
                'fee_type' => $this->systemConfigService->getFeeType(),
                'fee_value' => $this->systemConfigService->getFeeValue(),
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function updateFee(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fee_type' => 'required|in:fixed,percentage',
                'fee_value' => 'required|integer|min:1',
            ]);

            $this->systemConfigService->set('fee_type', $request->input('fee_type'));
            $this->systemConfigService->set('fee_value', (string) $request->input('fee_value'));

            return response()->json([
                'message' => 'Configuração de taxa atualizada com sucesso.',
                'fee_type' => $request->input('fee_type'),
                'fee_value' => $request->input('fee_value'),
            ], JsonResponse::HTTP_OK);
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

    public function getRevenue(Request $request): JsonResponse
    {
        try {
            $query = SystemRevenue::query();

            if ($request->has('date_start') && $request->input('date_start')) {
                $query->whereDate('created_at', '>=', $request->input('date_start'));
            }

            if ($request->has('date_end') && $request->input('date_end')) {
                $query->whereDate('created_at', '<=', $request->input('date_end'));
            }

            $entries = $query->orderBy('created_at', 'desc')->get();
            $totalCents = $entries->sum('amount_cents');

            return response()->json([
                'total_cents' => $totalCents,
                'entries' => $entries,
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
