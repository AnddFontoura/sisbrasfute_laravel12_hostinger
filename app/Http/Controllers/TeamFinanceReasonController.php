<?php

namespace App\Http\Controllers;

use App\Service\TeamFinanceReasonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamFinanceReasonController extends Controller
{
    public function __construct(
        protected TeamFinanceReasonService $teamFinanceReasonService,
    ) {}

    public function index(int $teamId): JsonResponse
    {
        try {
            $reasons = $this->teamFinanceReasonService->listByTeam($teamId);
            return response()->json($reasons, JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function store(Request $request, int $teamId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:1|max:100',
        ]);

        try {
            $reason = $this->teamFinanceReasonService->create($teamId, $request->only(['name']));
            return response()->json($reason, JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(Request $request, int $teamId, int $reasonId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:1|max:100',
        ]);

        try {
            $reason = $this->teamFinanceReasonService->update($teamId, $reasonId, $request->only(['name']));
            return response()->json($reason, JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy(int $teamId, int $reasonId): JsonResponse
    {
        try {
            $this->teamFinanceReasonService->delete($teamId, $reasonId);
            return response()->json(['success' => 'Razão removida com sucesso'], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
