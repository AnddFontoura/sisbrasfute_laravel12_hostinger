<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamFinanceCreateOrUpdateRequest;
use App\Models\TeamFinance;
use App\Repository\TeamFinanceRepository;
use App\Service\TeamFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeamFinanceController extends Controller
{
    public function __construct(
        protected TeamFinanceService $teamFinanceService,
        protected TeamFinanceRepository $teamFinanceRepository,
    ) {

    }

    public function index(Request $request, int $teamId): JsonResponse
    {
        $teamFinances = $this->teamFinanceRepository->getByTeamId($teamId);

        return response()->json($teamFinances, JsonResponse::HTTP_OK);
    }

    public function save(TeamFinanceCreateOrUpdateRequest $request, int $teamId, ?int $teamFinanceId = null): JsonResponse
    {
        $data = $request->validated();

        if ($teamId) {
            $teamFinanceId = $this->teamFinanceService->updateTeamFinance($data, $teamFinanceId);

            $message = "Time atualizado com sucesso";
        } else {
            $teamFinanceId = $this->teamFinanceService->createTeamFinance($data);

            $message = "Registro financeiro criado com sucesso";
        }

        return response()->json($message, JsonResponse::HTTP_CREATED);
    }

    public function show(int $teamId, int $id): JsonResponse
    {
        $teamFinance = $this->teamFinanceRepository->getById($id);

        return response()->json($teamFinance, JsonResponse::HTTP_OK);
    }
}
