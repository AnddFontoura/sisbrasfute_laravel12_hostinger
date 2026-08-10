<?php

namespace App\Service;

use App\Models\TeamFinance;
use App\Repository\TeamFinanceRepository;

class TeamFinanceService extends BaseService
{
    public function __construct(
        protected TeamFinanceRepository $teamFinanceRepository,
        protected TeamService $teamService,
        protected TeamFinanceReasonService $teamFinanceReasonService,
    ) {
    }

    public function createTeamFinance(array $data, int $teamId): TeamFinance
    {
        $this->teamService->checkIfTeamExists($teamId);

        $reasonId = $this->resolveReasonId($data, $teamId);

        $financeData = [
            'team_id' => $teamId,
            'match_id' => $data['matchId'] ?? null,
            'team_player_id' => $data['teamPlayerId'] ?? null,
            'description' => $data['description'] ?? null,
            'value' => $data['value'],
            'type' => $data['type'],
            'reason_id' => $reasonId,
        ];

        return $this->teamFinanceRepository->create($financeData);
    }

    public function updateTeamFinance(array $data, int $teamId, int $teamFinanceId): TeamFinance
    {
        $this->checkIfTeamFinanceBelongsToTeam($teamFinanceId, $teamId);

        $reasonId = $this->resolveReasonId($data, $teamId);

        $financeData = [
            'team_id' => $teamId,
            'match_id' => $data['matchId'] ?? null,
            'team_player_id' => $data['teamPlayerId'] ?? null,
            'description' => $data['description'] ?? null,
            'value' => $data['value'],
            'type' => $data['type'],
            'reason_id' => $reasonId,
        ];

        return $this->teamFinanceRepository->updateById($financeData, $teamFinanceId);
    }

    /**
     * Resolve the reason_id: if reasonName is provided, find or create the reason.
     * If reasonId is provided directly, use it. Otherwise null.
     */
    private function resolveReasonId(array $data, int $teamId): ?int
    {
        if (!empty($data['reasonName'])) {
            $reason = $this->teamFinanceReasonService->findOrCreate($teamId, $data['reasonName']);
            return $reason->id;
        }

        if (!empty($data['reasonId'])) {
            return (int) $data['reasonId'];
        }

        return null;
    }

    public function checkIfTeamFinanceBelongsToTeam(int $teamFinanceId, int $teamId): bool
    {
        $finance = $this->teamFinanceRepository->firstById($teamFinanceId);

        throw_if(!$finance || $finance->team_id !== $teamId, new \Exception(
            'Registro financeiro não encontrado',
            404
        ));

        return true;
    }
}
