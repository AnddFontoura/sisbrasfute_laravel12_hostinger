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
        $sql = $this->model->select(
            'teams.name',
            'teams.id',
            'teams.banner_path',
            'teams.user_id',
            'teams.logo_path',
            'cities.name as city_name',
            'states.name as state_name',
        )
        ->join('cities', 'cities.id', '=', 'teams.city_id')
        ->join('states', 'states.id', '=', 'cities.state_id');

        if (isset($filter['teamId'])) {
            $sql->where('team_id' , '=', $filter['teamId']);
        }

        if (!empty($filter['name'])) {
            $sql->where('teams.name', 'LIKE', '%' . $filter['name'] . '%');
        }

        if (!empty($filter['city_id'])) {
            $sql->where('teams.city_id', '=', $filter['city_id']);
        }

        if (!empty($filter['state_id'])) {
            $sql->where('states.id', '=', $filter['state_id']);
        }

        if (!empty($filter['modality_id'])) {
            $sql->where('teams.modality_id', '=', $filter['modality_id']);
        }

        return $sql
            ->orderBy('teams.name', $orderBy)
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
