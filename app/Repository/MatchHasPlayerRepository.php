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
        // Count how many slots exist for this game_position_id in this match
        $totalSlots = \App\Models\MatchesHasGamePositions::where('match_id', $matchId)
            ->where('game_position_id', $gamePositionId)
            ->count();

        // Count how many are already occupied
        $occupiedSlots = $this->model
            ->where('match_id', $matchId)
            ->where('game_position_id', $gamePositionId)
            ->whereNull('deleted_at')
            ->count();

        // If all slots are taken, return a truthy result (position is full)
        if ($occupiedSlots >= $totalSlots) {
            return $this->model
                ->where('match_id', $matchId)
                ->where('game_position_id', $gamePositionId)
                ->whereNull('deleted_at')
                ->first();
        }

        // There's still a free slot
        return null;
    }
}
