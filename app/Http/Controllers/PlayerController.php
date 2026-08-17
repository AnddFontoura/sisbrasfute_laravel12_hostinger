<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerCreateOrUpdateRequest;
use App\Repository\PlayerRepository;
use App\Service\PlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlayerController extends Controller
{
    public function __construct(
        protected PlayerRepository $playerRepository,
        protected PlayerService $playerService
    ) {

    }

    public function index(Request $request): JsonResponse
    {
        $players = $this->playerRepository->paginatedByName();

        return response()->json($players, JsonResponse::HTTP_OK);
    }

    public function save(PlayerCreateOrUpdateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->playerService->saveOrUpdate($data);

        return response()->json(['Dado atualizado com sucesso'], JsonResponse::HTTP_OK);
    }

    public function show(?int $id = null): JsonResponse
    {
        if ($id) {
            $player = $this->playerRepository->getById($id);
        } else {
            $player = $this->playerRepository->firstByUserId(Auth::id());
        }

        return response()->json($player, JsonResponse::HTTP_OK);
    }

    public function getPlayerTeams(int $playerId): JsonResponse
    {
        $player = $this->playerRepository->getById($playerId);

        if (!$player) {
            return response()->json([], JsonResponse::HTTP_OK);
        }

        $teams = \App\Models\TeamPlayer::where('user_id', $player->user_id)
            ->with('teamInfo')
            ->get()
            ->map(fn($tp) => [
                'id' => $tp->teamInfo?->id,
                'name' => $tp->teamInfo?->name,
                'logo_url' => $tp->teamInfo?->logo_url,
            ])
            ->filter(fn($t) => $t['id'] !== null)
            ->sortBy('name')
            ->values();

        return response()->json($teams, JsonResponse::HTTP_OK);
    }

    public function getPlayerMatches(int $playerId, Request $request): JsonResponse
    {
        $player = $this->playerRepository->getById($playerId);

        if (!$player) {
            return response()->json([], JsonResponse::HTTP_OK);
        }

        $limit = min((int) $request->input('limit', 5), 5);

        $teamPlayerIds = \App\Models\TeamPlayer::where('user_id', $player->user_id)->pluck('id');

        $matches = \App\Models\MatchHasPlayer::whereIn('team_player_id', $teamPlayerIds)
            ->with('matchInfo')
            ->get()
            ->pluck('matchInfo')
            ->unique('id')
            ->sortByDesc('schedule')
            ->take($limit)
            ->map(fn($match) => [
                'id' => $match->id,
                'my_team_name' => $match->my_team_name,
                'enemy_team_name' => $match->enemy_team_name,
                'my_team_score' => $match->my_team_score,
                'enemy_team_score' => $match->enemy_team_score,
                'schedule_br' => $match->schedule_br,
            ])
            ->values();

        return response()->json($matches, JsonResponse::HTTP_OK);
    }
}
