<?php

namespace App\Service;

use App\Repository\TeamPlayerRepository;

class TeamPlayerService extends BaseService
{
    public function __construct(
        protected TeamPlayerRepository $teamPlayerRepository,
        protected TeamService $teamService,
    ) {

    }

    public function getTeamMembersFromTeam(array $filter, int $teamId)
    {
        $this->teamService->checkIfTeamExists($teamId);

        return $this->teamPlayerRepository->getPlayersFromTeam($filter, $teamId);
    }
}
