<?php

namespace App\Repository;

use App\Models\MatchesHasGamePositions;

class MatchHasGamePositionRepository extends BaseRepository
{
    public function __construct(protected MatchesHasGamePositions $model)
    {

    }

    
}