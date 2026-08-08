<?php

namespace App\Service;

use App\Models\Matches;
use App\Repository\MatchHasGamePositionRepository;

class MatchHasGamePositionService extends BaseService
{
    public function __construct(
        protected MatchHasGamePositionRepository $repository,
    ){

    }

    public function createOrUpdateGamePosition(Matches $matches, array $matchData)
    {
        $teamCount = (int) ($matchData['teamsCount'] ?? 1);
        $positions = $matchData['positions'] ?? [];

        // Delete existing positions for this match before recreating
        $this->repository->deleteByMatchId($matches->id);

        for ($team = 1; $team <= $teamCount; $team++) {
            foreach ($positions as $position) {
                $this->repository->create([
                    'match_id' => $matches->id,
                    'game_position_id' => $position['game_position_id'],
                    'team_id' => $matches->created_by_team_id,
                    'player_id' => auth()->id(),
                    'team_reference' => $team,
                    'value' => $position['price'] ?? 0,
                ]);
            }
        }
    }
}