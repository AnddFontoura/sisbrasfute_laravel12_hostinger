<?php

namespace App\Repository;

use App\Models\MatchHasPlayer;

class MatchHasPlayerRepository extends BaseRepository
{
    public function __construct(MatchHasPlayer $model)
    {
        $this->model = $model;
    }

    public function findByMatchAndPosition(int $matchId, int $gamePositionId): ?MatchHasPlayer
    {
        return $this->model
            ->where('match_id', $matchId)
            ->where('game_position_id', $gamePositionId)
            ->first();
    }

    public function findByIdAndMatch(int $id, int $matchId): ?MatchHasPlayer
    {
        return $this->model
            ->where('id', $id)
            ->where('match_id', $matchId)
            ->first();
    }

    public function findActiveByMatchAndTeamPlayer(int $matchId, int $teamPlayerId): ?MatchHasPlayer
    {
        return $this->model
            ->where('match_id', $matchId)
            ->where('team_player_id', $teamPlayerId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function findActiveByMatchAndPosition(int $matchId, int $gamePositionId): ?MatchHasPlayer
    {
        return $this->model
            ->where('match_id', $matchId)
            ->where('game_position_id', $gamePositionId)
            ->whereNull('deleted_at')
            ->first();
    }
}
