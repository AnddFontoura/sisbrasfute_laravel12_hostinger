<?php

namespace App\Repository;

use App\Models\Matches;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminRepository
{
    /**
     * Get paginated users with computed fields for email verification and player profile existence.
     */
    public function getPaginatedUsers(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = User::query()
            ->select('id', 'name', 'email', 'email_verified_at', 'created_at')
            ->selectRaw('(email_verified_at IS NOT NULL) as email_verified')
            ->selectRaw('(EXISTS (SELECT 1 FROM players WHERE players.user_id = users.id AND players.deleted_at IS NULL)) as has_player_profile');

        if (!empty($filters['name'])) {
            $query->where('name', 'LIKE', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['email'])) {
            $query->where('email', 'LIKE', '%' . $filters['email'] . '%');
        }

        if (isset($filters['email_verified']) && $filters['email_verified'] !== null && $filters['email_verified'] !== '') {
            if ($filters['email_verified'] === 'true' || $filters['email_verified'] === true) {
                $query->whereNotNull('email_verified_at');
            } elseif ($filters['email_verified'] === 'false' || $filters['email_verified'] === false) {
                $query->whereNull('email_verified_at');
            }
        }

        if (isset($filters['has_player_profile']) && $filters['has_player_profile'] !== null && $filters['has_player_profile'] !== '') {
            if ($filters['has_player_profile'] === 'true' || $filters['has_player_profile'] === true) {
                $query->whereHas('playerProfile');
            } elseif ($filters['has_player_profile'] === 'false' || $filters['has_player_profile'] === false) {
                $query->whereDoesntHave('playerProfile');
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get a user with their player profile and game positions.
     */
    public function getUserWithProfile(int $userId): ?User
    {
        return User::with(['playerProfile', 'playerProfile.playerGamePositionInfo.gamePositionInfo'])
            ->find($userId);
    }

    /**
     * Get paginated teams with eager loaded city/state info.
     */
    public function getPaginatedTeams(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Team::query()
            ->with('cityInfo.stateInfo')
            ->select('id', 'name', 'city_id', 'modality_id', 'logo_path', 'banner_path');

        if (!empty($filters['name'])) {
            $query->where('name', 'LIKE', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['state_id'])) {
            $query->whereHas('cityInfo', function ($q) use ($filters) {
                $q->where('state_id', $filters['state_id']);
            });
        }

        if (!empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }

        if (!empty($filters['modality_id'])) {
            $query->where('modality_id', $filters['modality_id']);
        }

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    /**
     * Get paginated matches with eager loaded city/state info.
     */
    public function getPaginatedMatches(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        $query = Matches::query()
            ->with('cityInfo.stateInfo');

        if (!empty($filters['team_name'])) {
            $teamName = $filters['team_name'];
            $query->where(function ($q) use ($teamName) {
                $q->where('my_team_name', 'LIKE', '%' . $teamName . '%')
                    ->orWhere('enemy_team_name', 'LIKE', '%' . $teamName . '%');
            });
        }

        if (!empty($filters['state_id'])) {
            $query->whereHas('cityInfo', function ($q) use ($filters) {
                $q->where('state_id', $filters['state_id']);
            });
        }

        if (!empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }

        if (!empty($filters['date_start'])) {
            $query->where('schedule', '>=', $filters['date_start']);
        }

        if (!empty($filters['date_end'])) {
            $query->where('schedule', '<=', $filters['date_end']);
        }

        return $query->orderBy('schedule', 'desc')->paginate($perPage);
    }
}
