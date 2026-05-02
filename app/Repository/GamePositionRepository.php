<?php

namespace App\Repository;

use App\Models\GamePosition;
use Illuminate\Support\Collection;

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

    public function getOrderedByNameWithParameters(array $parameters): ?Collection
    {
        $teamId = $parameters['teamId'] ?? $parameters['teamID'] ?? null;

        $sql = $this->model
            ->select('game_positions.*')
            ->orderBy('game_positions.name', $parameters['order'] ?? 'asc');

        if ($teamId) {
            $sql->leftJoin('team_search_positions', function ($join) use ($teamId) {
                $join->on('team_search_positions.game_position_id', '=', 'game_positions.id')
                    ->where('team_search_positions.team_id', '=', $teamId)
                    ->whereNull('team_search_positions.deleted_at');
            })
                ->whereNull('team_search_positions.id');
        }

        return $sql->get();
    }
}
