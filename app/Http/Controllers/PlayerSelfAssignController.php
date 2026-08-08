<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerSelfAssignRequest;
use App\Service\MatchPositionService;
use Illuminate\Http\JsonResponse;

class PlayerSelfAssignController extends Controller
{
    public function __construct(
        protected MatchPositionService $matchPositionService,
    ) {

    }

    public function store(PlayerSelfAssignRequest $request, int $matchId): JsonResponse
    {
        try {
            $userId = auth()->id();

            $assignment = $this->matchPositionService->selfAssignPosition(
                $matchId,
                $request->game_position_id,
                $userId
            );

            return response()->json($assignment, JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy(int $matchId): JsonResponse
    {
        try {
            $userId = auth()->id();

            $this->matchPositionService->releasePosition($matchId, $userId);

            return response()->json(
                ['success' => 'Posição liberada com sucesso'],
                JsonResponse::HTTP_OK
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
