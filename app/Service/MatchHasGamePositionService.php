<?php

namespace App\Service;

use App\Repository\MatchHasGamePositionRepository;

class MatchHasGamePositionService extends BaseService
{
    public function __construct(
        MatchHasGamePositionRepository $repository,
    ){

    }

    public function setGamePositionsAvaliableForMatch(int $teamId, array $positionsAndValues)
    {
        foreach ($positionsAndValues as $pAndV) {
            
        }
    }
}