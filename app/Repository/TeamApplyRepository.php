<?php

namespace App\Repository;

use App\Models\TeamApplication;

class TeamApplyRepository extends BaseRepository
{
    public function __construct(
        TeamApplication $model
    ) {
        $this->model = $model;
    }

    public function firstByUserIdAndTeamId(int $playerId, int $teamId): ?TeamApplication
    {
        return $this->model
            ->where('player_id', $playerId)
            ->where('team_id', $teamId)
            ->first();
    }

    public function getTeamApplicationsPaginated(array $data, int $teamId, int $page)
    {
        $sql = $this->model
            ->with('playerInfo')
            ->where('team_id', $teamId);

        return $sql->paginate($this->paginateAmount, ['*'], $page);
    }
}