<?php

namespace App\Repository;

use App\Enums\CourtRole;
use App\Models\CourtMembership;

class CourtMembershipRepository extends BaseRepository
{
    public function __construct(CourtMembership $courtMembership)
    {
        $this->model = $courtMembership;
    }

    public function firstActive(int $courtId, int $userId): ?CourtMembership
    {
        return $this->model
            ->where('court_id', $courtId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function roleFor(int $courtId, int $userId): ?CourtRole
    {
        return $this->firstActive($courtId, $userId)?->role;
    }

    public function createMembership(int $courtId, int $userId, CourtRole $role): CourtMembership
    {
        // Enforce the "one active pivot per (court, user)" invariant before insert.
        $existing = $this->firstActive($courtId, $userId);

        if ($existing) {
            return $existing;
        }

        return $this->model->create([
            'court_id' => $courtId,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }

    public function members(int $courtId)
    {
        return $this->model
            ->with('user')
            ->where('court_id', $courtId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();
    }

    public function updateRole(int $courtId, int $userId, CourtRole $role): ?CourtMembership
    {
        $membership = $this->firstActive($courtId, $userId);

        if (!$membership) {
            return null;
        }

        $membership->update(['role' => $role]);

        return $membership;
    }

    public function softDeleteMembership(int $courtId, int $userId): void
    {
        $this->model
            ->where('court_id', $courtId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->get()
            ->each
            ->delete();
    }
}
