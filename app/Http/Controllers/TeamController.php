<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamCreateOrUpdateRequest;
use App\Http\Requests\TeamListRequest;
use App\Repository\TeamRepository;
use App\Repository\TeamSearchPositionRepository;
use App\Service\PlayerService;
use App\Service\TeamPlayerService;
use App\Service\TeamService;
use App\Service\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TeamController extends Controller
{
    public function __construct(
        protected UploadService $uploadService,
        protected TeamService $teamService,
        protected TeamRepository $teamRepository,
        protected TeamSearchPositionRepository $teamSearchPositionRepository,
        protected TeamPlayerService $teamPlayerService,
        protected PlayerService $playerService,
    ) {

    }

    public function index(TeamListRequest $request)
    {
        $filter = $request->validated();

        $teamList = $this->teamRepository->getPaginatedByName($filter);

        return response()->json($teamList, Response::HTTP_OK);
    }

    public function save(TeamCreateOrUpdateRequest $request, int $teamId = null): JsonResponse
    {
        $data = $request->validated();

        if ($teamId) {
            $teamInfo = $this->teamService->updateTeam($data, $teamId);

            $message = "Time atualizado com sucesso";
        } else {
            $teamInfo = $this->teamService->createTeam($data);

            $message = "Time criado com sucesso";
        }

        return response()->json($message, Response::HTTP_CREATED);
    }

    public function show(int $teamId): JsonResponse
    {
        $team = $this->teamRepository->getById($teamId);
        $team->isRecruiting = $this->teamSearchPositionRepository->getPositionsByTeam($teamId);

        return response()->json($team, Response::HTTP_OK);
    }

    public function listOfManagedTeamsByUser()
    {
        $user = Auth::user();
        $teamList = $this->teamRepository->getTeamsManagedByUser($user);

        return response()->json(['teams' => $teamList], Response::HTTP_OK);
    }

    public function deactivate(int $teamId): JsonResponse
    {
        $team = $this->teamRepository->getById($teamId);

        if (!$team) {
            return response()->json(['message' => 'Time não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $team->update(['status' => 0]);

        return response()->json(['message' => 'Time desativado com sucesso.'], Response::HTTP_OK);
    }

    public function reactivate(int $teamId): JsonResponse
    {
        $team = $this->teamRepository->getById($teamId);

        if (!$team) {
            return response()->json(['message' => 'Time não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $team->update(['status' => 1]);

        return response()->json(['message' => 'Time reativado com sucesso.'], Response::HTTP_OK);
    }

    public function performance(int $teamId): JsonResponse
    {
        $matches = \App\Models\Matches::where(function ($q) use ($teamId) {
                $q->where('my_team_id', $teamId)
                  ->orWhere('enemy_team_id', $teamId)
                  ->orWhere('created_by_team_id', $teamId);
            })
            ->whereNotNull('my_team_score')
            ->whereNotNull('enemy_team_score')
            ->whereNull('deleted_at')
            ->get();

        $statsByYear = [];

        foreach ($matches as $match) {
            $year = $match->schedule ? $match->schedule->format('Y') : 'Sem data';

            if (!isset($statsByYear[$year])) {
                $statsByYear[$year] = [
                    'year' => $year,
                    'matches' => 0,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                    'goals_scored' => 0,
                    'goals_conceded' => 0,
                ];
            }

            // Determine if this team is "my_team" or "enemy_team" in the match
            $isMyTeam = ($match->my_team_id == $teamId || $match->created_by_team_id == $teamId) && $match->enemy_team_id != $teamId;

            if ($isMyTeam) {
                $scored = (int) $match->my_team_score;
                $conceded = (int) $match->enemy_team_score;
            } else {
                $scored = (int) $match->enemy_team_score;
                $conceded = (int) $match->my_team_score;
            }

            $statsByYear[$year]['matches']++;
            $statsByYear[$year]['goals_scored'] += $scored;
            $statsByYear[$year]['goals_conceded'] += $conceded;

            if ($scored > $conceded) {
                $statsByYear[$year]['wins']++;
            } elseif ($scored === $conceded) {
                $statsByYear[$year]['draws']++;
            } else {
                $statsByYear[$year]['losses']++;
            }
        }

        // Sort by year descending
        krsort($statsByYear);

        return response()->json(array_values($statsByYear), JsonResponse::HTTP_OK);
    }

    public function myTeamsFull(): JsonResponse
    {
        $user = Auth::user();

        // Teams the user administrates (is owner)
        $administered = $this->teamRepository->getTeamsManagedByUser($user);

        // Teams the user is a member of (but not the owner)
        $memberTeams = \App\Models\Team::select('teams.*')
            ->join('team_players', 'team_players.team_id', '=', 'teams.id')
            ->where('team_players.user_id', $user->id)
            ->where('teams.user_id', '!=', $user->id)
            ->whereNull('team_players.deleted_at')
            ->whereNull('teams.deleted_at')
            ->get();

        return response()->json([
            'administered' => $administered,
            'member' => $memberTeams,
        ], Response::HTTP_OK);
    }

    public function teamApply(int $teamId)
    {
        $user = Auth::user();
        $this->teamPlayerService->applyToTeam($user, $teamId);
    }
}
