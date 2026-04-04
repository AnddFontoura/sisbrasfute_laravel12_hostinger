<?php

namespace App\Service;

use App\Repository\ModalityRepository;

class ModalityService
{
    public function __construct(
        protected ModalityRepository $modalityRepository,
    ) {

    }
    public function updatePlayerModalities(array $modalities, int $playerId): void
    {
        $this->modalityRepository->deleteModalitiesOfPlayer($playerId);

        foreach ($modalities as $modality) {
            $exists = $this->modalityRepository->getPlayerModality($playerId, $modality);

            if ($exists) {
                $exists->restore();
            } else {
                $this->modalityRepository->create([
                    'modality_id' => $modality,
                    'player_id' => $playerId,
                ]);
            }
        }
    }
}
