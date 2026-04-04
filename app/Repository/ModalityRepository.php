<?php

namespace App\Repository;

use App\Models\Modality;

class ModalityRepository extends BaseRepository
{
    public function __construct(Modality $model)
    {
        $this->model = $model;
    }

    public function getFirstByPlayerId(int $playerId): ?Modality
    {
        return $this->model
            ->where('player_id', $playerId)
            ->first();
    }

    public function deleteModalitiesOfPlayer(int $playerId): void
    {
        $this->model
            ->where('player_id', $playerId)
            ->delete();
    }

    public function getPlayerModality(int $playerId, int $modalityId): ?Modality
    {
        return $this->model
                ->where('modality_id', $modalityId)
                ->where('player_id', $playerId)
                ->withTrashed()
                ->first();
    }
}
