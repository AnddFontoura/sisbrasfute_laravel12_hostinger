<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamFinanceCreateOrUpdateRequest;
use App\Repository\TeamFinanceRepository;
use App\Service\TeamFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamFinanceController extends Controller
{
    public function __construct(
        protected TeamFinanceService $teamFinanceService,
        protected TeamFinanceRepository $teamFinanceRepository,
    ) {
    }

    public function index(Request $request, int $teamId): JsonResponse
    {
        $filters = $request->only([
            'type', 'reason_id', 'team_player_id', 'match_id',
            'date_start', 'date_end', 'value_min', 'value_max',
        ]);
        $perPage = (int) $request->query('per_page', 15);

        $teamFinances = $this->teamFinanceRepository->getByTeamId($teamId, $filters, $perPage);

        return response()->json($teamFinances, JsonResponse::HTTP_OK);
    }

    public function save(TeamFinanceCreateOrUpdateRequest $request, int $teamId, ?int $teamFinanceId = null): JsonResponse
    {
        $data = $request->validated();

        if ($teamFinanceId) {
            $this->teamFinanceService->updateTeamFinance($data, $teamId, $teamFinanceId);
            $message = "Registro financeiro atualizado com sucesso";
        } else {
            $this->teamFinanceService->createTeamFinance($data, $teamId);
            $message = "Registro financeiro criado com sucesso";
        }

        return response()->json(['message' => $message], JsonResponse::HTTP_CREATED);
    }

    public function show(int $teamId, int $id): JsonResponse
    {
        $teamFinance = $this->teamFinanceRepository->getById($id);

        if (!$teamFinance || $teamFinance->team_id !== $teamId) {
            return response()->json(
                ['message' => 'Registro não encontrado'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        return response()->json($teamFinance, JsonResponse::HTTP_OK);
    }
}
