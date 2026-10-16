<?php

namespace App\Jobs;

use App\Models\Matches;
use App\Service\MatchNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fans out the "new match" e-mail notifications off the RabbitMQ queue.
 *
 * Previously MatchesService::createOrUpdateMatch called
 * MatchNotificationService::notifyNewMatch() inline, which iterated over every
 * eligible player and pushed one queued mail per player onto the (database)
 * queue — all inside the HTTP request that creates the match. On a team with
 * many players that means N synchronous writes before the request can return,
 * which is what made "open a match" feel slow.
 *
 * Now the request dispatches a single message to RabbitMQ and returns
 * immediately. A worker picks this job up and does the per-player fan-out
 * there, off the request path.
 *
 * It targets the RabbitMQ connection (same pattern as ProcessPaymentWebhookJob)
 * on the dedicated `emails` queue, so e-mail work never competes with — or gets
 * blocked behind — payment settlement. A worker must drain that queue (see
 * routes/console.php).
 */
class SendNewMatchEmailsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retry a few times with growing backoff before giving up. */
    public int $tries = 3;

    /** Seconds between retries: 30s, 2m, 5m. */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $matchId)
    {
        // Route to the dedicated RabbitMQ e-mail queue so the fan-out never
        // touches the request path and never shares a queue with payments.
        $this->onConnection('rabbitmq');
        $this->onQueue(config('queue.rabbitmq_queues.emails', 'emails'));
    }

    public function handle(MatchNotificationService $matchNotificationService): void
    {
        // Load the fresh match with the relation the mail/content needs.
        $match = Matches::with('cityInfo')->find($this->matchId);

        if (!$match) {
            Log::warning('SendNewMatchEmailsJob: match not found', [
                'match_id' => $this->matchId,
            ]);
            return;
        }

        // Reuse the existing eligibility + per-player mail logic. Each e-mail is
        // itself a queued Mailable (NewMatchMail implements ShouldQueue), so the
        // actual SMTP delivery also stays off this worker's critical path.
        $matchNotificationService->notifyNewMatch($match);
    }
}
