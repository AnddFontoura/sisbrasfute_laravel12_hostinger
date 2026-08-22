<?php

namespace App\Repository;

use App\Models\Matches;

class MatchesRepository extends BaseRepository
{
    public function __construct(Matches $matches)
    {
        $this->model = $matches;
    }

    public function getOrderedByMatchDate(array $filter, string $orderBy = 'asc')
    {
        $sql = $this->model
            ->with('cityInfo.stateInfo')
            ->orderBy('schedule', $orderBy);

        if (isset($filter['teamId'])) {
            $sql->where('created_by_team_id', $filter['teamId']);
        }

        // Only show active matches in public search
        $sql->where('status', 1);

        return $sql->paginate(12);
    }

    public function getById(int $id)
    {
        return $this->model
            ->with(['cityInfo.stateInfo', 'myTeamInfo', 'enemyTeamInfo', 'positions.gamePositionInfo', 'tag'])
            ->where('id', $id)
            ->first();
    }

}
