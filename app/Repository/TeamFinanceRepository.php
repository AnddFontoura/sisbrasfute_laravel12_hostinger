<?php

namespace App\Repository;

use App\Models\TeamFinance;

class TeamFinanceRepository extends BaseRepository
{
    public function __construct(TeamFinance $model)
    {
        $this->model = $model;
    }

    public function getByTeamId(int $teamId)
    {
        return $this->model
            ->with(['matchInfo', 'teamPlayerInfo', 'reasonInfo'])
            ->where('team_id', $teamId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getById(int $id)
    {
        return $this->model
            ->with(['matchInfo', 'teamPlayerInfo', 'reasonInfo'])
            ->where('id', $id)
            ->first();
    }
}
