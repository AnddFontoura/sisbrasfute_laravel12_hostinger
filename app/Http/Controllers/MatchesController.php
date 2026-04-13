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


    public function save(MatchesCreateOrUpdateRequest $request, int $matchId = null): JsonResponse
    {
        $data = $request->validated();

        $this->matchesService->createOrUpdateMatch($data, $matchId);

        return response()->json(['success' => 'Partida criada ou atualizada com sucesso'], JsonResponse::HTTP_OK);
    }

    public function show(int $matchId): JsonResponse
    {
        $team = $this->matchesRepository->getById($matchId);

        return response()->json($team, JsonResponse::HTTP_OK);
    }
}
