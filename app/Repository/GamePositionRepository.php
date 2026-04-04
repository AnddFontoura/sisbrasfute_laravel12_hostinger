<?php

namespace App\Repository;

use App\Models\GamePosition;

class GamePositionRepository extends BaseRepository
{
    public function __construct(GamePosition $model)
    {
        $this->model = $model;
    }

    public function getFirstByPlayerId(int $playerId): ?GamePosition
    {
        return $this->model
            ->where('player_id', $playerId)
            ->first();
    }

    public function deleteGamePositionsOfPlayer(int $playerId): void
    {
        $this->model
            ->where('player_id', $playerId)
            ->delete();
    }

    public function getPlayerGamePosition(int $playerId, int $gamePositionId): ?GamePosition
    {
        return $this->model
            ->where('game_position_id', $gamePositionId)
            ->where('player_id', $playerId)
            ->withTrashed()
            ->first();
    }
}
