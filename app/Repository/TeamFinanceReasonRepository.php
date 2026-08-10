<?php

namespace App\Repository;

use App\Models\TeamFinanceReason;

class TeamFinanceReasonRepository extends BaseRepository
{
    public function __construct(TeamFinanceReason $model)
    {
        $this->model = $model;
    }

    public function getByTeamId(int $teamId)
    {
        return $this->model
            ->where(function ($query) use ($teamId) {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $teamId);
            })
            ->orderBy('name')
            ->get();
    }

    public function existsByNameAndTeam(string $name, int $teamId, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where(function ($q) use ($teamId) {
                $q->whereNull('team_id')
                    ->orWhere('team_id', $teamId);
            })
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))]);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function findOrCreateByNameAndTeam(string $name, int $teamId): TeamFinanceReason
    {
        // First check if a global or team-specific reason with this name already exists
        $existing = $this->model
            ->where(function ($q) use ($teamId) {
                $q->whereNull('team_id')
                    ->orWhere('team_id', $teamId);
            })
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Create as team-specific
        return $this->model->create([
            'team_id' => $teamId,
            'name' => trim($name),
        ]);
    }
}
