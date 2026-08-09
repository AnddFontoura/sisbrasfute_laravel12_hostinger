<?php

namespace App\Repository;

use App\Models\GamePosition;
use App\Models\PlayerHasGamePosition;
use Illuminate\Support\Collection;

class GamePositionRepository extends BaseRepository
{
    protected $pivotModel;

    public function __construct(GamePosition $model, PlayerHasGamePosition $pivotModel)
    {
        $this->model = $model;
        $this->pivotModel = $pivotModel;
    }

    public function getFirstByPlayerId(int $playerId): ?PlayerHasGamePosition
    {
        return $this->pivotModel
            ->where('player_id', $playerId)
            ->first();
    }

    public function deleteGamePositionsOfPlayer(int $playerId): void
    {
        $this->pivotModel
            ->where('player_id', $playerId)
            ->delete();
    }

    public function getPlayerGamePosition(int $playerId, int $gamePositionId): ?PlayerHasGamePosition
    {
        return $this->pivotModel
            ->where('game_position_id', $gamePositionId)
            ->where('player_id', $playerId)
            ->withTrashed()
            ->first();
    }

    public function createPlayerGamePosition(array $data): PlayerHasGamePosition
    {
        return $this->pivotModel->create($data);
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
