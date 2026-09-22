<?php

namespace App\Repository;

use App\Models\TeamUniform;

class TeamUniformRepository extends BaseRepository
{
    public function __construct(TeamUniform $model)
    {
        $this->model = $model;
    }

    public function getByTeamId(int $teamId)
    {
        return $this->model
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get();
    }

    public function existsByNameAndTeam(string $name, int $teamId, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where('team_id', $teamId)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))]);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
