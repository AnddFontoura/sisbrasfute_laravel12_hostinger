<?php

namespace App\Repository;

use App\Models\TeamSearchPosition;

class TeamSearchPositionRepository extends BaseRepository
{
    public function __construct(
        TeamSearchPosition $model
    ) {
        $this->model = $model;
    }

    public function getPositionsByTeam(int $teamId)
    {
        return $this->model
            ->where('team_id', $teamId)
            ->with('gamePositionInfo')
            ->get();
    }

    public function createOrReactivateGamePosition(int $gamePositionId, int $teamId)
    {
        $teamSearchPosition = $this->model
            ->withTrashed()
            ->where('game_position_id', $gamePositionId)
            ->where('team_id', $teamId)
            ->first();

        if ($teamSearchPosition) {
            $teamSearchPosition->restore();
            $teamSearchPosition->touch();
            $teamSearchPosition->save();
        } else {
            $teamSearchPosition = $this->model->create([
                'game_position_id' => $gamePositionId,
                'team_id' => $teamId,
            ]);
        }
    }

    public function deleteTeamSearchPositions(int $teamId, int $id): void
    {
        $this->model
            ->where('team_id', $teamId)
            ->where('id', $id)
            ->delete();
    }
}