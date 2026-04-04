<?php

namespace App\Repository;

use App\Models\Matches;

class MatchesRepository extends BaseRepository
{
    public function __construct(Matches $matches)
    {
        $this->model = $matches;
    }

    public function getOrderedByMatchDate(string $orderBy = 'asc')
    {
        return $this->model
            ->with('cityInfo.stateInfo')
            ->orderBy('schedule', $orderBy)
            ->get();
    }

    public function getById(int $id)
    {
        return $this->model
            ->with('cityInfo.stateInfo')
            ->where('id', $id)
            ->first();
    }

}
