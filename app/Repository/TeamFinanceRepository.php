<?php

namespace App\Repository;

use App\Models\TeamFinance;

class TeamFinanceRepository extends BaseRepository
{
    public function __construct(TeamFinance $model)
    {
        $this->model = $model;
    }

    public function getByTeamId(int $teamId, array $filters = [], int $perPage = 15)
    {
        $query = $this->model
            ->with(['matchInfo', 'teamPlayerInfo', 'reasonInfo'])
            ->where('team_id', $teamId);

        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['reason_id'])) {
            $query->where('reason_id', $filters['reason_id']);
        }

        if (!empty($filters['team_player_id'])) {
            $query->where('team_player_id', $filters['team_player_id']);
        }

        if (!empty($filters['match_id'])) {
            $query->where('match_id', $filters['match_id']);
        }

        if (!empty($filters['date_start'])) {
            $query->whereDate('created_at', '>=', $filters['date_start']);
        }

        if (!empty($filters['date_end'])) {
            $query->whereDate('created_at', '<=', $filters['date_end']);
        }

        if (!empty($filters['value_min'])) {
            $query->where('value', '>=', $filters['value_min']);
        }

        if (!empty($filters['value_max'])) {
            $query->where('value', '<=', $filters['value_max']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getById(int $id)
    {
        return $this->model
            ->with(['matchInfo', 'teamPlayerInfo', 'reasonInfo'])
            ->where('id', $id)
            ->first();
    }
}
