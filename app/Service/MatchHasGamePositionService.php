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

        // Intra-team match ("Partida entre o time"): the squad is split into
        // several sub-teams. We generate one identical slot set per sub-team,
        // all owned by the creator team but tagged with a distinct
        // team_reference (1 = Time A, 2 = Time B, ...), so the frontend can
        // render one list per sub-team side by side.
        if ($matches->match_type === \App\Enums\MatchType::Normal && !$matches->enemy_team_id) {
            $teamsCount = max(1, (int) ($matches->teams_count ?? 1));

            for ($reference = 1; $reference <= $teamsCount; $reference++) {
                $this->createPositionsForTeam($matches, $matches->created_by_team_id, $reference, $positions);
            }

            return;
        }

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
