<?php

namespace App\Service;

use App\Repository\PlayerHasModalitiesRepository;

class PlayerHasModalitiesService extends BaseService
{
    public function __construct(
        protected PlayerHasModalitiesRepository $playerHasModalitiesRepository,
    ) {
    }
    public function updatePlayerModalities(array $modalities, int $playerId): void
    {
        $this->playerHasModalitiesRepository->deleteModalitiesOfPlayer($playerId);

        foreach ($modalities as $modality) {
            $exists = $this->playerHasModalitiesRepository->getPlayerModality($playerId, $modality);

            if ($exists) {
                $exists->restore();
            } else {
                $this->playerHasModalitiesRepository->create([
                    'modality_id' => $modality,
                    'player_id' => $playerId,
                ]);
            }
        }
    }
}