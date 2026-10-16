<?php

namespace App\Service;

use App\Models\Matches;
use App\Repository\MatchHasGamePositionRepository;
use App\Repository\MatchHasPlayerRepository;

class MatchHasGamePositionService extends BaseService
{
    public function __construct(
        protected MatchHasGamePositionRepository $repository,
        protected MatchHasPlayerRepository $matchHasPlayerRepository,
    ){

    }

    /**
     * Reconciles the position slots for a match against the desired set.
     *
     * Slots are kept for the creator team and, when an opponent already exists
     * (enemy_team_id), mirrored for the opponent team as well. Each slot
     * carries the real team_id of the side it belongs to.
     *
     * On edit we reuse existing slot ids instead of wiping everything and
     * recreating, because match_has_player references the slot id
     * (match_has_game_position_id). Preserving the ids keeps the players
     * already assigned to each position in place through the edit.
     */
    public function createOrUpdateGamePosition(Matches $matches, array $matchData)
    {
        $positions = $matchData['positions'] ?? [];

        // Keep track of the slot ids that are still part of the desired
        // configuration so that, at the end, we can remove only the slots that
        // were actually dropped. We intentionally do NOT wipe everything and
        // recreate: slot ids are referenced by match_has_player
        // (match_has_game_position_id), so deleting + recreating would orphan
        // every player already assigned to a position. Reconciling in place
        // preserves those ids and therefore the players' assignments.
        $keptSlotIds = [];

        // Intra-team match ("Partida entre o time"): the squad is split into
        // several sub-teams. We generate one identical slot set per sub-team,
        // all owned by the creator team but tagged with a distinct
        // team_reference (1 = Time A, 2 = Time B, ...), so the frontend can
        // render one list per sub-team side by side.
        if ($matches->match_type === \App\Enums\MatchType::Normal && !$matches->enemy_team_id) {
            $teamsCount = max(1, (int) ($matches->teams_count ?? 1));

            for ($reference = 1; $reference <= $teamsCount; $reference++) {
                $keptSlotIds = array_merge(
                    $keptSlotIds,
                    $this->syncPositionsForTeam($matches, $matches->created_by_team_id, $reference, $positions)
                );
            }

            $this->pruneRemovedSlots($matches, $keptSlotIds);

            return;
        }

        // Always sync the creator team side.
        $keptSlotIds = array_merge(
            $keptSlotIds,
            $this->syncPositionsForTeam($matches, $matches->created_by_team_id, 1, $positions)
        );

        // Mirror to the opponent side when it is already defined.
        if ($matches->enemy_team_id) {
            $keptSlotIds = array_merge(
                $keptSlotIds,
                $this->syncPositionsForTeam($matches, $matches->enemy_team_id, 2, $positions)
            );
        }

        // Remove only the slots that are no longer part of the configuration.
        $this->pruneRemovedSlots($matches, $keptSlotIds);
    }

    /**
     * Removes the position slots that are no longer part of the configuration
     * and clears any player assignment that referenced them, so we never leave
     * a match_has_player row pointing to a deleted slot.
     *
     * @param array<int, int> $keptSlotIds
     */
    private function pruneRemovedSlots(Matches $matches, array $keptSlotIds): void
    {
        $removedSlotIds = $this->repository
            ->getByMatchExcludingIds($matches->id, $keptSlotIds)
            ->pluck('id')
            ->all();

        if (empty($removedSlotIds)) {
            return;
        }

        // Release players assigned to the slots that are about to be removed.
        $this->matchHasPlayerRepository->deleteByMatchPositionSlotIds($matches->id, $removedSlotIds);

        // Then drop the slots themselves.
        $this->repository->deleteByMatchExcludingIds($matches->id, $keptSlotIds);
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

    /**
     * Reconciles the position slots of one team side (team_id + team_reference)
     * against the desired set, reusing existing slot ids whenever possible.
     *
     * Existing slots are matched to desired positions by game_position_id.
     * A matched slot is updated in place (keeping its id, so any
     * match_has_player assignment that references it stays valid), while
     * desired positions without a match create a brand new slot. Slots left
     * unmatched are returned to the caller through the list of kept ids being
     * absent, so they can be pruned afterwards.
     *
     * @param array<int, array{game_position_id: int, price?: float|int}> $positions
     * @return array<int, int> the ids of the slots that were kept (updated or created)
     */
    private function syncPositionsForTeam(Matches $matches, int $teamId, int $teamReference, array $positions): array
    {
        // Current slots for this exact side, grouped by game_position_id so we
        // can reuse one existing slot per desired position (supporting
        // multiple slots of the same position).
        $existingBySlotPosition = $this->repository
            ->getByMatchTeamAndReference($matches->id, $teamId, $teamReference)
            ->groupBy('game_position_id')
            ->map(fn ($group) => $group->values());

        $consumed = [];
        $keptSlotIds = [];

        foreach ($positions as $position) {
            $gamePositionId = $position['game_position_id'];
            $value = $position['price'] ?? 0;

            $group = $existingBySlotPosition->get($gamePositionId);
            $index = $consumed[$gamePositionId] ?? 0;

            if ($group && $group->count() > $index) {
                // Reuse an existing slot: update its price only. Keeping the id
                // intact preserves the player already assigned to this slot.
                $slot = $group->get($index);
                $consumed[$gamePositionId] = $index + 1;

                $this->repository->updateById(['value' => $value], $slot->id);
                $keptSlotIds[] = $slot->id;

                continue;
            }

            // No reusable slot left for this position: create a new one.
            $created = $this->repository->create([
                'match_id' => $matches->id,
                'game_position_id' => $gamePositionId,
                'team_id' => $teamId,
                'player_id' => auth()->id(),
                'team_reference' => $teamReference,
                'value' => $value,
            ]);

            $keptSlotIds[] = $created->id;
        }

        return $keptSlotIds;
    }
}
