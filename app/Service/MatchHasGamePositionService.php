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

    /**
     * (Re)creates the position slots for a match.
     *
     * Slots are created for the creator team and, when an opponent already
     * exists (enemy_team_id), mirrored for the opponent team as well. Each
     * slot carries the real team_id of the side it belongs to.
     */
    public function createOrUpdateGamePosition(Matches $matches, array $matchData)
    {
        $positions = $matchData['positions'] ?? [];

        // Delete existing positions for this match before recreating.
        $this->repository->deleteByMatchId($matches->id);

        // Always create the creator team side.
        $this->createPositionsForTeam($matches, $matches->created_by_team_id, 1, $positions);

        // Mirror to the opponent side when it is already defined.
        if ($matches->enemy_team_id) {
            $this->createPositionsForTeam($matches, $matches->enemy_team_id, 2, $positions);
        }
    }

    /**
     * Creates position slots for the opponent team of a match, mirroring the
     * position set already configured for the creator team. Used when a
     * challenge is confirmed (enemy_team_id becomes known after creation).
     */
    public function mirrorPositionsForEnemyTeam(Matches $matches): void
    {
        if (!$matches->enemy_team_id) {
            return;
        }

        // Avoid duplicating if the enemy side already has slots.
        $alreadyMirrored = $this->repository->existsForMatchAndTeam($matches->id, $matches->enemy_team_id);
        if ($alreadyMirrored) {
            return;
        }

        // Derive the position set from the creator team's existing slots.
        $creatorPositions = $this->repository
            ->getByMatchAndTeam($matches->id, $matches->created_by_team_id)
            ->map(fn ($p) => [
                'game_position_id' => $p->game_position_id,
                'price' => $p->value,
            ])
            ->values()
            ->all();

        if (empty($creatorPositions)) {
            return;
        }

        $this->createPositionsForTeam($matches, $matches->enemy_team_id, 2, $creatorPositions);
    }

    /**
     * @param array<int, array{game_position_id: int, price?: float|int}> $positions
     */
    private function createPositionsForTeam(Matches $matches, int $teamId, int $teamReference, array $positions): void
    {
        foreach ($positions as $position) {
            $this->repository->create([
                'match_id' => $matches->id,
                'game_position_id' => $position['game_position_id'],
                'team_id' => $teamId,
                'player_id' => auth()->id(),
                'team_reference' => $teamReference,
                'value' => $position['price'] ?? 0,
            ]);
        }
    }
}
