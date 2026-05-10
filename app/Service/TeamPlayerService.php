<?php

namespace App\Service;

use App\Repository\PlayerRepository;
use App\Repository\TeamPlayerRepository;
use App\Repository\TeamSearchPositionRepository;
use Symfony\Component\HttpFoundation\Response;

class TeamPlayerService extends BaseService
{
    public function __construct(
        protected TeamPlayerRepository $teamPlayerRepository,
        protected TeamSearchPositionService $teamSearchPositionService,
        protected TeamService $teamService,
        protected PlayerService $playerService,
        protected PlayerRepository $playerRepository,
        protected TeamSearchPositionRepository $teamSearchPositionRepository,
    ) {

    }

    public function getTeamMembersFromTeam(array $filter, int $teamId)
    {
        $this->teamService->checkIfTeamExists($teamId);

        return $this->teamPlayerRepository->getPlayersFromTeam($filter, $teamId);
    }

}
