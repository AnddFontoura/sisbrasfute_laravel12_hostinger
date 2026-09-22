<?php

namespace App\Repository;

use App\Models\TeamPlayerUniformNumber;

class TeamPlayerUniformNumberRepository extends BaseRepository
{
    public function __construct(TeamPlayerUniformNumber $model)
    {
        $this->model = $model;
    }

    /**
     * All numbers a team player owns, grouped by uniform.
     */
    public function getByTeamPlayer(int $teamPlayerId)
    {
        return $this->model
            ->where('team_player_id', $teamPlayerId)
            ->orderBy('team_uniform_id')
            ->orderBy('number')
            ->get();
    }

    /**
     * Numbers a team player owns for a specific uniform.
     */
    public function getByTeamPlayerAndUniform(int $teamPlayerId, int $uniformId)
    {
        return $this->model
            ->where('team_player_id', $teamPlayerId)
            ->where('team_uniform_id', $uniformId)
            ->orderBy('number')
            ->get();
    }

    public function existsForPlayerUniformNumber(int $teamPlayerId, int $uniformId, int $number): bool
    {
        return $this->model
            ->where('team_player_id', $teamPlayerId)
            ->where('team_uniform_id', $uniformId)
            ->where('number', $number)
            ->exists();
    }

    public function deleteByPlayerAndUniform(int $teamPlayerId, int $uniformId): void
    {
        $this->model
            ->where('team_player_id', $teamPlayerId)
            ->where('team_uniform_id', $uniformId)
            ->delete();
    }
}
