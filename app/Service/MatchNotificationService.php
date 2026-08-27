<?php

namespace App\Service;

use App\Mail\NewMatchMail;
use App\Mail\PositionAvailableMail;
use App\Models\Matches;
use App\Models\TeamPlayer;
use App\Repository\TeamPlayerRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MatchNotificationService extends BaseService
{
    public function __construct(
        protected TeamPlayerRepository $teamPlayerRepository,
    ) {}

    /**
     * Notify eligible players about a newly created match.
     */
    public function notifyNewMatch(Matches $match): void
    {
        $eligiblePlayers = $this->getEligiblePlayers($match);

        foreach ($eligiblePlayers as $player) {
            $email = $player->userInfo?->email;

            if (empty($email)) {
                continue;
            }

            try {
                Mail::to($email)->queue(
                    new NewMatchMail($match, $player->name)
                );
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch match notification', [
                    'team_player_id' => $player->id,
                    'email' => $email,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Notify eligible players about an available position after a cancellation/refund.
     * If the match has a tag_id, only players with that tag are notified.
     * If the match has no tag_id, all active team players are notified.
     */
    public function notifyPositionAvailable(Matches $match): void
    {
        $eligiblePlayers = $this->getEligiblePlayers($match);

        foreach ($eligiblePlayers as $player) {
            $email = $player->userInfo?->email;

            if (empty($email)) {
                continue;
            }

            try {
                Mail::to($email)->queue(
                    new PositionAvailableMail($match, $player->name)
                );
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch position available notification', [
                    'team_player_id' => $player->id,
                    'email' => $email,
                    'match_id' => $match->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get players eligible to receive match notifications.
     * Filters by tag if the match has a tag_id configured.
     */
    protected function getEligiblePlayers(Matches $match): Collection
    {
        $query = TeamPlayer::where('team_id', $match->created_by_team_id)
            ->whereNotNull('user_id')
            ->where('active', true)
            ->where('notify_match', true)
            ->whereNull('deleted_at')
            ->with('userInfo');

        if ($match->tag_id) {
            $query->whereHas('tags', function ($q) use ($match) {
                $q->where('team_tag_id', $match->tag_id);
            });
        }

        return $query->get();
    }
}
