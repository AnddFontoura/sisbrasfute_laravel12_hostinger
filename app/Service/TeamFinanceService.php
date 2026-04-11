<?php

namespace App\Service;

use App\Models\TeamFinance;
use App\Repository\TeamFinanceRepository;

class TeamFinanceService extends BaseService
{
    public function __construct(
        protected TeamFinanceRepository $teamFinanceRepository,
        protected TeamService $teamService,
    ) {
    }

    public function createTeamFinance(array $data): TeamFinance
    {
        $this->teamService->checkIfTeamExists($data['teamId']);

        return $this->teamFinanceRepository->create($data);
    }

    public function updateTeamFinance(array $data, int $teamFinanceId)
    {
        $this->checkIfTeamFinanceBelongsToTeam($teamFinanceId, $data['teamId']);

        return $this->teamFinanceRepository->updateById($data, $teamFinanceId);
    }

    public function checkIfTeamFinanceBelongsToTeam(int $teamFinanceId, int $teamId): bool
    {
        return $this->teamFinanceRepository->getById($teamFinanceId)->team_id === $teamId;
    }
}
