<?php

namespace App\Http\Controllers;

use App\Http\Requests\MatchesCreateOrUpdateRequest;
use App\Http\Requests\TeamListRequest;
use App\Repository\MatchesRepository;
use App\Service\MatchesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchesController extends Controller
{
    public function __construct(
        protected MatchesService $matchesService,
        protected MatchesRepository $matchesRepository,
    ) {

    }

    public function index(TeamListRequest $request): JsonResponse
    {
        $filter = $request->validated();

        $matchesList = $this->matchesRepository->getOrderedByMatchDate($filter, 'desc');

        return response()->json($matchesList, JsonResponse::HTTP_OK);
    }


    public function save(MatchesCreateOrUpdateRequest $request, ?int $matchId = null): JsonResponse
    {
        $data = $request->validated();

        // Se é uma edição, verificar se o usuário é dono do time que criou a partida
        if ($matchId) {
            $match = $this->matchesRepository->getById($matchId);

            if (!$match) {
                return response()->json(
                    ['message' => 'Partida não encontrada.'],
                    JsonResponse::HTTP_NOT_FOUND
                );
            }

            $team = \App\Models\Team::find($match->created_by_team_id);

            if (!$team || $team->user_id !== auth()->id()) {
                return response()->json(
                    ['message' => 'Você não tem permissão para editar esta partida.'],
                    JsonResponse::HTTP_FORBIDDEN
                );
            }
        }

        $this->matchesService->createOrUpdateMatch($data, $matchId);

        return response()->json(['success' => 'Partida criada ou atualizada com sucesso'], JsonResponse::HTTP_OK);
    }

    public function show(int $matchId): JsonResponse
    {
        $match = $this->matchesRepository->getById($matchId);

        if (!$match) {
            return response()->json(
                ['message' => 'Partida não encontrada'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        return response()->json($match, JsonResponse::HTTP_OK);
    }

    public function deactivate(int $matchId): JsonResponse
    {
        $match = $this->matchesRepository->getById($matchId);

        if (!$match) {
            return response()->json(['message' => 'Partida não encontrada.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $team = \App\Models\Team::find($match->created_by_team_id);

        if (!$team || $team->user_id !== auth()->id()) {
            return response()->json(['message' => 'Você não tem permissão para desativar esta partida.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $match->update(['status' => 0]);

        return response()->json(['message' => 'Partida desativada com sucesso.'], JsonResponse::HTTP_OK);
    }

    public function reactivate(int $matchId): JsonResponse
    {
        $match = $this->matchesRepository->getById($matchId);

        if (!$match) {
            return response()->json(['message' => 'Partida não encontrada.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $team = \App\Models\Team::find($match->created_by_team_id);

        if (!$team || $team->user_id !== auth()->id()) {
            return response()->json(['message' => 'Você não tem permissão para reativar esta partida.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $match->update(['status' => 1]);

        return response()->json(['message' => 'Partida reativada com sucesso.'], JsonResponse::HTTP_OK);
    }

    public function myMatches(): JsonResponse
    {
        $user = auth()->user();

        // Matches created by teams the user owns
        $teamIds = \App\Models\Team::where('user_id', $user->id)->pluck('id');

        $matches = \App\Models\Matches::with('cityInfo.stateInfo', 'myTeamInfo', 'enemyTeamInfo')
            ->whereIn('created_by_team_id', $teamIds)
            ->orderBy('schedule', 'desc')
            ->get();

        return response()->json($matches, JsonResponse::HTTP_OK);
    }
}
