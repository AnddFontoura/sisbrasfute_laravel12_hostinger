<?php

namespace App\Service;

use App\Mail\AdminNotificationMail;
use App\Models\Matches;
use App\Models\MatchHasPlayer;
use App\Models\Notification;
use App\Models\Player;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Repository\NotificationRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService extends BaseService
{
    public const TYPE_GAME = 'game';
    public const TYPE_TEAM = 'team';
    public const TYPE_SYSTEM = 'system';

    public function __construct(
        protected NotificationRepository $notificationRepository,
    ) {}

    /**
     * Core creator: persists one notification row plus one recipient row per user.
     * Optionally e-mails each recipient (used by admin-originated notifications).
     *
     * @param  array<int>  $userIds
     */
    public function notify(
        string $type,
        string $title,
        string $description,
        array $userIds,
        ?int $teamId = null,
        ?int $matchId = null,
        ?int $createdBy = null,
        bool $sendEmail = false,
    ): ?Notification {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if (empty($userIds)) {
            return null;
        }

        $notification = $this->notificationRepository->create([
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'team_id' => $teamId,
            'match_id' => $matchId,
            'created_by' => $createdBy,
            'sent_by_email' => $sendEmail,
        ]);

        $this->notificationRepository->attachRecipients($notification->id, $userIds);

        if ($sendEmail) {
            $this->dispatchEmails($notification, $userIds);
        }

        return $notification;
    }

    /**
     * Queue e-mails for the given recipients (admin notifications only).
     *
     * @param  array<int>  $userIds
     */
    protected function dispatchEmails(Notification $notification, array $userIds): void
    {
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'email']);

        foreach ($users as $user) {
            if (empty($user->email)) {
                continue;
            }

            try {
                Mail::to($user->email)->queue(
                    new AdminNotificationMail($notification, $user->name)
                );
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch admin notification email', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /* ===================== USER-FACING READ OPERATIONS ===================== */

    public function listForUser(int $userId, array $filters, int $perPage = 15)
    {
        return $this->notificationRepository->paginateForUser($userId, $filters, $perPage);
    }

    public function latestForUser(int $userId, int $limit = 5)
    {
        return $this->notificationRepository->latestForUser($userId, $limit);
    }

    public function unreadCount(int $userId): int
    {
        return $this->notificationRepository->unreadCountForUser($userId);
    }

    public function markAsRead(int $notificationUserId, int $userId): void
    {
        $this->notificationRepository->markAsRead($notificationUserId, $userId);
    }

    public function markAllAsRead(int $userId): int
    {
        return $this->notificationRepository->markAllAsRead($userId);
    }

    /* ===================== ADMIN OPERATIONS ===================== */

    /**
     * Admin: send to a target audience.
     * $data = [type, title, description, audience, userIds?, teamId?, matchId?]
     * audience in: all | users | team | match
     */
    public function adminSend(array $data, int $createdBy): ?Notification
    {
        $audience = $data['audience'];
        $teamId = null;
        $matchId = null;

        switch ($audience) {
            case 'all':
                $userIds = $this->allUserIds();
                break;
            case 'users':
                $userIds = $data['userIds'] ?? [];
                break;
            case 'team':
                $teamId = $data['teamId'] ?? null;
                $userIds = $this->teamPlayerUserIds((int) $teamId);
                break;
            case 'match':
                $matchId = $data['matchId'] ?? null;
                $userIds = $this->matchPlayerUserIds((int) $matchId);
                break;
            default:
                $userIds = [];
        }

        return $this->notify(
            type: $data['type'],
            title: $data['title'],
            description: $data['description'],
            userIds: $userIds,
            teamId: $teamId,
            matchId: $matchId,
            createdBy: $createdBy,
            sendEmail: true,
        );
    }

    public function adminList(array $filters, int $perPage = 15)
    {
        return $this->notificationRepository->paginateAdminSent($filters, $perPage);
    }

    public function adminShow(int $id): ?Notification
    {
        return $this->notificationRepository->findWithRelations($id);
    }

    /**
     * Admin: edit an already-sent notification (title/description/type).
     * Editing a single row reflects to every recipient automatically.
     */
    public function adminUpdate(int $id, array $data): ?Notification
    {
        $notification = $this->notificationRepository->firstById($id);

        if (!$notification) {
            return null;
        }

        $notification->update([
            'type' => $data['type'] ?? $notification->type,
            'title' => $data['title'] ?? $notification->title,
            'description' => $data['description'] ?? $notification->description,
        ]);

        return $notification->fresh();
    }

    /* ===================== AUTOMATIC TRIGGERS ===================== */

    /**
     * Trigger: user accepted into a team.
     */
    public function notifyUserAcceptedIntoTeam(int $userId, int $teamId, string $teamName): void
    {
        $this->notify(
            type: self::TYPE_TEAM,
            title: 'Você foi aceito em um time!',
            description: "Sua entrada no time {$teamName} foi confirmada. Bem-vindo!",
            userIds: [$userId],
            teamId: $teamId,
        );
    }

    /**
     * Trigger: a new match was created for a team -> notify active roster players.
     * Also notifies players (with a profile) in the same city as the match.
     */
    public function notifyNewMatch(Matches $match): void
    {
        $teamName = $match->my_team_name ?? '';
        $title = 'Nova partida do seu time';
        $description = "Uma nova partida foi criada para o time {$teamName}.";

        $teamUserIds = $this->teamPlayerUserIds($match->created_by_team_id, $match->tag_id);

        $this->notify(
            type: self::TYPE_GAME,
            title: $title,
            description: $description,
            userIds: $teamUserIds,
            teamId: $match->created_by_team_id,
            matchId: $match->id,
        );

        // Players in the same city as the match (excluding those already notified above).
        if ($match->city_id) {
            $cityUserIds = array_diff($this->cityPlayerUserIds($match->city_id), $teamUserIds);

            if (!empty($cityUserIds)) {
                $cityName = $match->cityInfo?->name ?? 'sua cidade';

                $this->notify(
                    type: self::TYPE_GAME,
                    title: 'Nova partida na sua cidade',
                    description: "Uma nova partida foi criada em {$cityName}.",
                    userIds: $cityUserIds,
                    matchId: $match->id,
                );
            }
        }
    }

    /**
     * Trigger: a match a player is involved in was updated -> notify involved players.
     */
    public function notifyMatchUpdated(Matches $match): void
    {
        $involved = $this->matchPlayerUserIds($match->id);

        $this->notify(
            type: self::TYPE_GAME,
            title: 'Partida alterada',
            description: 'Uma partida em que você está envolvido foi alterada. Confira os detalhes.',
            userIds: $involved,
            teamId: $match->created_by_team_id,
            matchId: $match->id,
        );
    }

    /**
     * Trigger: a player left a match -> notify eligible players NOT currently on the list.
     */
    public function notifyPositionAvailable(Matches $match): void
    {
        $eligible = $this->teamPlayerUserIds($match->created_by_team_id, $match->tag_id);
        $onList = $this->matchPlayerUserIds($match->id);

        $userIds = array_values(array_diff($eligible, $onList));

        $this->notify(
            type: self::TYPE_GAME,
            title: 'Vaga disponível em uma partida',
            description: 'Abriu uma vaga em uma partida do seu time. Garanta seu lugar!',
            userIds: $userIds,
            teamId: $match->created_by_team_id,
            matchId: $match->id,
        );
    }

    /* ===================== AUDIENCE RESOLVERS ===================== */

    /**
     * @return array<int>
     */
    protected function allUserIds(): array
    {
        return User::query()->pluck('id')->all();
    }

    /**
     * Active team players' user ids (optionally filtered by match tag).
     *
     * @return array<int>
     */
    protected function teamPlayerUserIds(int $teamId, ?int $tagId = null): array
    {
        $query = TeamPlayer::query()
            ->where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->where('active', true)
            ->whereNull('deleted_at');

        if ($tagId) {
            $query->whereHas('tags', function ($q) use ($tagId) {
                $q->where('team_tag_id', $tagId);
            });
        }

        return $query->pluck('user_id')->unique()->values()->all();
    }

    /**
     * User ids of players assigned to a match's player list.
     *
     * @return array<int>
     */
    protected function matchPlayerUserIds(int $matchId): array
    {
        return MatchHasPlayer::query()
            ->where('match_id', $matchId)
            ->whereNull('deleted_at')
            ->with('teamPlayerInfo:id,user_id')
            ->get()
            ->pluck('teamPlayerInfo.user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * User ids of players whose current city matches the given city.
     *
     * @return array<int>
     */
    protected function cityPlayerUserIds(int $cityId): array
    {
        return Player::query()
            ->where('city_id', $cityId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }
}
