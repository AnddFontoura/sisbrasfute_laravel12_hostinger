<?php

namespace App\Http\Controllers;

use App\Models\TeamFinance;
use App\Repository\TeamFinanceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamFinanceController extends Controller
{
    public function __construct(
        protected TeamFinanceRepository $teamFinanceRepository,
    ) {

    }

    public function index(Request $request, int $teamId): JsonResponse
    {
        $teamFinances = $this->teamFinanceRepository->getByTeamId($teamId);

        return response()->json($teamFinances, JsonResponse::HTTP_OK);
    }

    public function save(int $teamId): JsonResponse
    {


        return response()->json('Tudo certo', JsonResponse::HTTP_OK);
    }

    public function show(int $teamId, int $id): JsonResponse
    {
        $teamFinance = $this->teamFinanceRepository->getById($id);

        return response()->json($teamFinance, JsonResponse::HTTP_OK);
    }
}
