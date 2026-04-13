<?php

namespace App\Repository;

use App\Models\Team;
use App\Models\User;

class TeamRepository extends BaseRepository
{
    public function __construct(Team $team)
    {
        $this->model = $team;
    }

    public function getPaginatedByName(array $filter, string $orderBy = 'asc')
    {
        $sql = $this->model;

        if (isset($filter['teamId'])) {
            $sql->where('team_id' , '=', $filter['teamId']);
        }

        return $sql
            ->orderBy('name', $orderBy)
            ->get();
    }

    public function getById(int $id)
    {
        return $this->model
            ->with('cityInfo.stateInfo')
            ->where('id', $id)
            ->first();
    }

    public function getTeamsManagedByUser(User $user)
    {
        return $this->model
            ->where('user_id', $user->id)
            ->get();
    }
}
