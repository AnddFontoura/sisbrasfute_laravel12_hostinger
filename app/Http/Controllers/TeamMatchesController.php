<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamMatchesCreateOrUpdateRequest;
use App\Http\Requests\TeamMatchesLeaveRequest;
use App\Http\Requests\TeamMatchesJoinRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamMatchesController extends Controller
{
    public function createTeamMatch(TeamMatchesCreateOrUpdateRequest $request): JsonResponse
    {

        return response()->json(['success' => 'Partida criada com sucesso'], JsonResponse::HTTP_OK);
    }

    public function joinTeamMatch(TeamMatchesJoinRequest $request): JsonResponse
    {

        return response()->json(['success' => 'Partida criada com sucesso'], JsonResponse::HTTP_OK);
    }

    public function leaveTeamMatch(TeamMatchesLeaveRequest $request): JsonResponse
    {

        return response()->json(['success' => 'Partida criada com sucesso'], JsonResponse::HTTP_OK);
    }
}
