<?php

namespace App\Repository;

use App\Models\PlayerHasModality;
use Illuminate\Database\Eloquent\Collection;

class PlayerHasModalitiesRepository extends BaseRepository
{
    public function __construct(PlayerHasModality $model)
    {
        $this->model = $model;
    }
    public function getFirstByPlayerId(int $playerId): ?PlayerHasModality
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

    public function getPlayerModality(int $playerId, int $modalityId): ?Collection
    {
        return $this->model
            ->where('modality_id', $modalityId)
            ->where('player_id', $playerId)
            ->withTrashed()
            ->first();
    }
}