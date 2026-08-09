<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamPlayerCreateOrUpdateRequest;
use App\Http\Requests\TeamPlayerListRequest;
use App\Service\TeamPlayerService;
use Illuminate\Http\JsonResponse;

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

    public function show(int $teamId, int $playerId)
    {
        $teamPlayer = $this->teamPlayerService->getTeamPlayer($teamId, $playerId);

        return response()->json($teamPlayer->load('tags'), JsonResponse::HTTP_OK);
    }

    public function save(TeamPlayerCreateOrUpdateRequest $request, int $teamId, ?int $playerId = null): JsonResponse
    {
        try {
            $data = $request->validated();

            $teamPlayer = $this->teamPlayerService->saveOrUpdate($data, $teamId, $playerId);

            // Sync tags if provided
            if ($request->has('tag_ids')) {
                $teamPlayer->tags()->sync($request->input('tag_ids', []));
            }

            return response()->json($teamPlayer->load('tags'), JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            $statusCode = $e->getCode();
            // Ensure we have a valid HTTP status code
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
            }

            return response()->json(
                ['message' => $e->getMessage()],
                $statusCode
            );
        }
    }
}
