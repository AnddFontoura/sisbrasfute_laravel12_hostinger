<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamPlayerListRequest;
use App\Service\TeamPlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamPlayerController extends Controller
{
    public function __construct(
        protected TeamPlayerService $teamPlayerService,
    ) {

    }

    public function index(TeamPlayerListRequest $request, int $teamId)
    {
        $data = $request->validated();

        $teamPlayers = $this->teamPlayerService->getTeamMembersFromTeam($data, $teamId);

        return response()->json($teamPlayers, JsonResponse::HTTP_OK);
    }
}
