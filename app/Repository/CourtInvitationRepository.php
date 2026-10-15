<?php

namespace App\Repository;

use App\Enums\CourtInvitationStatus;
use App\Models\CourtInvitation;
use Illuminate\Pagination\LengthAwarePaginator;

class CourtInvitationRepository extends BaseRepository
{
    public function __construct(CourtInvitation $courtInvitation)
    {
        $this->model = $courtInvitation;
    }

    public function getByCourtPaginated(int $courtId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('court_id', $courtId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findPendingByEmailAndCourt(string $email, int $courtId): ?CourtInvitation
    {
        return $this->model
            ->where('email', $email)
            ->where('court_id', $courtId)
            ->where('status', CourtInvitationStatus::Pending->value)
            ->whereNull('deleted_at')
            ->first();
    }

    public function getReceivedByEmail(string $email): LengthAwarePaginator
    {
        return $this->model
            ->with('courtInfo.photos')
            ->where('email', $email)
            ->where('status', CourtInvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->whereNull('deleted_at')
            ->paginate($this->paginateAmount);
    }

    public function findByToken(string $token): ?CourtInvitation
    {
        return $this->model
            ->where('token', $token)
            ->whereNull('deleted_at')
            ->first();
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

        return (bool) $invitation->delete();
    }

    public function markExpiredBulk(): int
    {
        return $this->model
            ->where('status', CourtInvitationStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->whereNull('deleted_at')
            ->update(['status' => CourtInvitationStatus::Expired->value]);
    }
}
