<?php

namespace App\Repository;

use App\Models\Notification;
use App\Models\NotificationUser;
use Illuminate\Support\Carbon;

class NotificationRepository extends BaseRepository
{
    public function __construct(Notification $model)
    {
        $this->model = $model;
    }

    /**
     * Attach recipients (users) to a notification, avoiding duplicates.
     *
     * @param  array<int>  $userIds
     */
    public function attachRecipients(int $notificationId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        foreach ($userIds as $userId) {
            NotificationUser::firstOrCreate([
                'notification_id' => $notificationId,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Paginated list of notifications for a given user, with optional date/type filters.
     */
    public function paginateForUser(int $userId, array $filters, int $perPage = 15)
    {
        $query = NotificationUser::query()
            ->where('user_id', $userId)
            ->with(['notificationInfo.teamInfo', 'notificationInfo.matchInfo'])
            ->whereHas('notificationInfo', function ($q) use ($filters) {
                $this->applyNotificationFilters($q, $filters);
            });

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Latest N notifications for the bell dropdown.
     */
    public function latestForUser(int $userId, int $limit = 5)
    {
        return NotificationUser::query()
            ->where('user_id', $userId)
            ->with(['notificationInfo.teamInfo', 'notificationInfo.matchInfo'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function unreadCountForUser(int $userId): int
    {
        return NotificationUser::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(int $notificationUserId, int $userId): ?NotificationUser
    {
        $pivot = NotificationUser::query()
            ->where('id', $notificationUserId)
            ->where('user_id', $userId)
            ->first();

        if (!$pivot) {
            return null;
        }

        if ($pivot->read_at === null) {
            $pivot->read_at = Carbon::now();
            $pivot->save();
        }

        return $pivot;
    }

    public function markAllAsRead(int $userId): int
    {
        return NotificationUser::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * Paginated list of notifications created by admins (for the admin management screen).
     */
    public function paginateAdminSent(array $filters, int $perPage = 15)
    {
        $query = $this->model
            ->newQuery()
            ->whereNotNull('created_by')
            ->withCount('recipients')
            ->with(['teamInfo', 'matchInfo', 'createdBy']);

        $this->applyNotificationFilters($query, $filters);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function findWithRelations(int $id): ?Notification
    {
        return $this->model
            ->newQuery()
            ->with(['teamInfo', 'matchInfo', 'createdBy'])
            ->withCount('recipients')
            ->where('id', $id)
            ->first();
    }

    protected function applyNotificationFilters($query, array $filters): void
    {
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['date_start'])) {
            $query->whereDate('created_at', '>=', $filters['date_start']);
        }

        if (!empty($filters['date_end'])) {
            $query->whereDate('created_at', '<=', $filters['date_end']);
        }

        if (!empty($filters['title'])) {
            $query->where('title', 'LIKE', '%' . $filters['title'] . '%');
        }
    }
}
