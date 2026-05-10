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

    public function teamApply(int $teamId)
    {
        $user = Auth::user();
        $this->teamPlayerService->applyToTeam($user, $teamId);
    }
}
