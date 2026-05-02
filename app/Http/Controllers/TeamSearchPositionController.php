<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamSearchPositionCreateOrUpdateRequest;
use App\Http\Requests\TeamSearchPositionListRequest;
use App\Repository\TeamSearchPositionRepository;
use App\Service\TeamSearchPositionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamSearchPositionController extends Controller
{
    public function __construct(
        protected TeamSearchPositionService $teamSearchPositionService,
        protected TeamSearchPositionRepository $teamSearchPositionRepository,
    ) {

    }
    public function index(TeamSearchPositionListRequest $request, int $teamId)
    {
        $teamSearchingPositions = $this->teamSearchPositionRepository->getPositionsByTeam($teamId);

        return response()->json($teamSearchingPositions, Response::HTTP_OK);
    }

    public function save(TeamSearchPositionCreateOrUpdateRequest $request, int $teamId)
    {
        $data = $request->validated();

        $this->teamSearchPositionService->createOrUpdateTeamSearchPosition($data, $teamId);

    }

    public function delete(int $teamId, string $id)
    {
        $this->teamSearchPositionRepository->deleteTeamSearchPositions($teamId, $id);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
