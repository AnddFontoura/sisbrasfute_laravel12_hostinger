<?php

namespace App\Service;

use App\Repository\GamePositionRepository;

class GamePositionService
{
    public function __construct(
        protected GamePositionRepository $gamePositionRepository
    ) {
    }

    public function updatePlayerGamePosition(array $gamePositions, int $playerId)
    {
        $this->gamePositionRepository->deleteGamePositionsOfPlayer($playerId);

        foreach ($gamePositions as $gamePosition) {
            $gpExists = $this->gamePositionRepository->getPlayerGamePosition($playerId, $gamePosition);

            if ($gpExists) {
                $gpExists->restore();
            } else {
                $this->gamePositionRepository->create([
                    'game_position_id' => $gamePosition,
                    'player_id' => $playerId,
                ]);
            }
        }
    }
}
