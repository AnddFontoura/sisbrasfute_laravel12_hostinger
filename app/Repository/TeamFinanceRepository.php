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
            ->where('team_id', $teamId)
            ->get();
    }
}
