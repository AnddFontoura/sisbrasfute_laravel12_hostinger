<?php

namespace App\Service;

use App\Contracts\PaymentGatewayContract;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Front door for inbound payment webhooks.
 *
 * Responsibility: accept the raw payload, persist it durably, deduplicate
 * re-deliveries, and hand off to the RabbitMQ-backed job — all fast enough to
 * return 200 to the gateway within its timeout. No settlement happens here.
 */
class WebhookIngestService extends BaseService
{
    public function __construct(
        protected PaymentGatewayContract $paymentGateway,
        protected SystemConfigService $systemConfigService,
    ) {}

    /**
     * Ingests a raw webhook payload: records it (idempotently) and enqueues
     * processing. Returns the WebhookEvent (new or the pre-existing duplicate).
     *
     * @param array<string, mixed> $payload
     */
    public function ingest(array $payload): WebhookEvent
    {
        $provider = $this->systemConfigService->getPaymentGateway('efi');

        // Reuse the gateway's parser to resolve the charge reference + status.
        // This never credits anything; it only reads the payload shape.
        $parsed = $this->paymentGateway->handleWebhook($payload);

        $dedupKey = $this->buildDedupKey($provider, $parsed->chargeId, $parsed->status->value, $payload);

        // firstOrCreate on the unique dedup_key makes re-deliveries a no-op:
        // the DB unique index guarantees exactly one row even under a race.
        $created = false;
        $event = DB::transaction(function () use ($dedupKey, $provider, $parsed, $payload, &$created) {
            $existing = WebhookEvent::where('dedup_key', $dedupKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $created = true;

            return WebhookEvent::create([
                'provider' => $provider,
                'charge_reference' => $parsed->chargeId !== 'unknown' ? $parsed->chargeId : null,
                'dedup_key' => $dedupKey,
                'status' => WebhookEvent::STATUS_RECEIVED,
                'payload' => $payload,
            ]);
        });

        if (!$created) {
            Log::info('Webhook re-delivery ignored (dedup)', [
                'dedup_key' => $dedupKey,
                'webhook_event_id' => $event->id,
            ]);

            return $event;
        }

        // Hand off to the durable RabbitMQ queue; return 200 right after.
        ProcessPaymentWebhookJob::dispatch($event->id);

        return $event;
    }

    /**
     * Builds a stable deduplication key. Prefers the gateway charge reference
     * plus the settled status; falls back to a payload hash for payloads with
     * no resolvable reference so nothing is silently dropped.
     */
    private function buildDedupKey(string $provider, string $chargeId, string $status, array $payload): string
    {
        if ($chargeId !== '' && $chargeId !== 'unknown') {
            return "{$provider}:{$chargeId}:{$status}";
        }

        return "{$provider}:raw:" . hash('sha256', json_encode($payload));
    }
}
