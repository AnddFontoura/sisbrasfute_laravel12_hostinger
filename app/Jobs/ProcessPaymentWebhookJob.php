<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Service\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Settles a single inbound payment webhook off the RabbitMQ queue.
 *
 * The HTTP endpoint persists the raw event (WebhookEvent) and dispatches this
 * job, then returns 200 to the gateway immediately. Actual settlement runs
 * here with retries, so a slow DB or transient failure never costs us the
 * payment: RabbitMQ redelivers until it succeeds, and the WalletService
 * settlement is idempotent (row lock + unique gateway_reference), so a
 * redelivery cannot double-credit.
 *
 * ShouldBeUnique keyed by the WebhookEvent id prevents two copies of the same
 * event being worked concurrently.
 */
class ProcessPaymentWebhookJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retry a handful of times with growing backoff before failing. */
    public int $tries = 5;

    /** Seconds between retries: 10s, 30s, 60s, 2m, 5m. */
    public array $backoff = [10, 30, 60, 120, 300];

    /** Keep the unique lock for at most 10 minutes as a safety release. */
    public int $uniqueFor = 600;

    public function __construct(public readonly int $webhookEventId)
    {
        // Dedicated durable buffer for payments. Explicit so the app's default
        // queue connection is left untouched.
        $this->onConnection('rabbitmq');
        $this->onQueue(config('queue.rabbitmq_queues.payments', 'payments'));
    }

    public function uniqueId(): string
    {
        return (string) $this->webhookEventId;
    }

    public function handle(WalletService $walletService): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if (!$event) {
            Log::warning('ProcessPaymentWebhookJob: event not found', [
                'webhook_event_id' => $this->webhookEventId,
            ]);
            return;
        }

        // Already settled by a prior delivery — nothing to do.
        if ($event->status === WebhookEvent::STATUS_PROCESSED) {
            return;
        }

        $event->increment('attempts');

        try {
            // Settlement itself is idempotent in WalletService (locks the
            // pending transaction and only credits once).
            $walletService->handleWebhook($event->payload);

            $event->update([
                'status' => WebhookEvent::STATUS_PROCESSED,
                'processed_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $event->update([
                'status' => WebhookEvent::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);

            Log::error('ProcessPaymentWebhookJob failed', [
                'webhook_event_id' => $event->id,
                'charge_reference' => $event->charge_reference,
                'error' => $e->getMessage(),
            ]);

            // Rethrow so the queue retries (and eventually lands in failed_jobs).
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $event = WebhookEvent::find($this->webhookEventId);
        $event?->update([
            'status' => WebhookEvent::STATUS_FAILED,
            'last_error' => $e->getMessage(),
        ]);
    }
}
