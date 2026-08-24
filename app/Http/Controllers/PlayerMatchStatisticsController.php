<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerMatchStatisticsRequest;
use App\Models\Matches;
use App\Models\Team;
use App\Service\PlayerMatchStatisticsService;
use Illuminate\Http\JsonResponse;

class PlayerMatchStatisticsController extends Controller
{
    public function __construct(
        protected PlayerMatchStatisticsService $playerMatchStatisticsService,
    ) {

    }

    /**
     * GET /matches/{matchId}/statistics
     * Retorna todos os jogadores escalados com suas estatísticas.
     */
    public function index(int $matchId): JsonResponse
    {
        $match = Matches::find($matchId);

        if (!$match) {
            return response()->json(
                ['message' => 'Partida não encontrada.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $team = Team::find($match->created_by_team_id);

        if (!$team || $team->user_id !== auth()->id()) {
            return response()->json(
                ['message' => 'Você não tem permissão para gerenciar estatísticas desta partida.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $this->playerMatchStatisticsService->getMatchStatistics($matchId);

        return response()->json($data, JsonResponse::HTTP_OK);
    }

    /**
     * POST /matches/{matchId}/statistics
     * Cria ou atualiza estatísticas de múltiplos jogadores (bulk upsert).
     */
    public function store(PlayerMatchStatisticsRequest $request, int $matchId): JsonResponse
    {
        $match = Matches::find($matchId);

        if (!$match) {
            return response()->json(
                ['message' => 'Partida não encontrada.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $team = Team::find($match->created_by_team_id);

        if (!$team || $team->user_id !== auth()->id()) {
            return response()->json(
                ['message' => 'Você não tem permissão para gerenciar estatísticas desta partida.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $validated = $request->validated();

        $this->playerMatchStatisticsService->upsertStatistics($matchId, $validated['statistics']);

        return response()->json(
            ['message' => 'Estatísticas salvas com sucesso.'],
            JsonResponse::HTTP_OK
        );
    }

    /**
     * GET /team-player/{teamId}/statistics/{teamPlayerId}
     * Retorna estatísticas acumuladas do jogador no time.
     */
    public function playerAccumulated(int $teamId, int $teamPlayerId): JsonResponse
    {
        $team = Team::find($teamId);

        if (!$team) {
            return response()->json(
                ['message' => 'Time não encontrado.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        if ($team->user_id !== auth()->id()) {
            return response()->json(
                ['message' => 'Você não tem permissão para gerenciar estatísticas desta partida.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $this->playerMatchStatisticsService->getPlayerAccumulatedStats($teamPlayerId, $teamId);

        return response()->json($data, JsonResponse::HTTP_OK);
    }
}
