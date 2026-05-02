<?php

namespace App\Service;

use App\Repository\TeamSearchPositionRepository;

class TeamSearchPositionService
{
    public function __construct(
        protected TeamSearchPositionRepository $teamSearchPositionRepository,
    ) {

    }

    public function createOrUpdateTeamSearchPosition(array $data, int $teamId): void
    {
        //$this->teamSearchPositionRepository->deleteTeamSearchPositions($teamId);

        foreach ($data['game_position_id'] as $gamePositionId) {
            $this->teamSearchPositionRepository->createOrReactivateGamePosition($gamePositionId, $teamId);
        }
    }
}