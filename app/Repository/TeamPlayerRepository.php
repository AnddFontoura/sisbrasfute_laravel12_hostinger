<?php

namespace App\Repository;

use App\Models\TeamPlayer;

class TeamPlayerRepository extends BaseRepository
{
    public function __construct(
        TeamPlayer $model
    ) {
        $this->model = $model;
    }

    public function getPlayersFromTeam(array $filter, int $teamId)
    {
        $sql = $this->model
            ->where('team_id', $teamId);

        if (isset($filter['showDeleted']) && $filter['showDeleted'] === 'true') {
            $sql->withTrashed();
        }

        return $sql->orderBy('name', 'asc')
            ->paginate(12);
    }
}
