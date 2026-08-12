<?php

namespace App\Repository;

use App\Models\PlayerInvitation;
use Illuminate\Pagination\LengthAwarePaginator;

class PlayerInvitationRepository extends BaseRepository
{
    public function __construct(
        PlayerInvitation $model
    ) {
        $this->model = $model;
    }

    public function getByTeamPaginated(int $teamId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findPendingByEmailAndTeam(string $email, int $teamId): ?PlayerInvitation
    {
        return $this->model
            ->where('email', $email)
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function getReceivedByEmail(string $email): LengthAwarePaginator
    {
        return $this->model
            ->with('teamInfo')
            ->where('email', $email)
            ->whereNull('team_player_id')
            ->whereNull('deleted_at')
            ->paginate($this->paginateAmount);
    }

    public function softDeleteById(int $id): bool
    {
        $invitation = $this->model
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$invitation) {
            return false;
        }

        return $invitation->delete();
    }
}
