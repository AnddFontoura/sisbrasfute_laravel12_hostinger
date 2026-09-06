<?php

namespace App\Http\Controllers;

use App\Http\Requests\MatchPositionPaymentRequest;
use App\Http\Requests\MatchPositionSaveRequest;
use App\Service\MatchPositionService;
use Illuminate\Http\JsonResponse;

class MatchPositionController extends Controller
{
    public function __construct(
        protected MatchPositionService $matchPositionService,
    ) {

    }

    public function index(int $matchId): JsonResponse
    {
        try {
            $positions = $this->matchPositionService->getPositionsWithPlayers($matchId);

            return response()->json($positions, JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function save(MatchPositionSaveRequest $request, int $matchId): JsonResponse
    {
        try {
            $assignment = $this->matchPositionService->savePlayerPosition($matchId, $request->validated());

            return response()->json($assignment, JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function updatePayment(MatchPositionPaymentRequest $request, int $matchId, int $atribuicaoId): JsonResponse
    {
        try {
            $this->matchPositionService->updatePayment($matchId, $atribuicaoId, $request->price_payed);

            return response()->json(['success' => 'Pagamento atualizado com sucesso'], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }
}
