<?php

namespace App\Jobs;

use App\Models\WalletTransaction;
use App\Service\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Safety net for lost webhooks.
 *
 * A durable queue protects us once a webhook reaches the app, but it cannot
 * recover a confirmation the gateway never managed to deliver. This job scans
 * every still-pending charge old enough for a webhook to have been expected
 * and re-settles it through WalletService::settleCharge(), which re-queries
 * the gateway for the authoritative status — the exact same verified path the
 * webhook worker uses, so it is idempotent and cannot double-credit.
 */
class ReconcilePendingPaymentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param int $minAgeMinutes Only reconcile charges older than this (give the
     *                           webhook a fair chance first).
     * @param int $maxAgeHours   Stop looking after this age (expired charges).
     */
    public function __construct(
        public readonly int $minAgeMinutes = 2,
        public readonly int $maxAgeHours = 48,
    ) {}

    public function handle(WalletService $walletService): void
    {
        $pending = WalletTransaction::where('status', 'pending')
            ->whereNotNull('gateway_reference')
            ->where('created_at', '<=', now()->subMinutes($this->minAgeMinutes))
            ->where('created_at', '>=', now()->subHours($this->maxAgeHours))
            ->pluck('gateway_reference');

        foreach ($pending as $chargeId) {
            try {
                $walletService->settleCharge($chargeId);
            } catch (Throwable $e) {
                Log::warning('Reconciliation failed for charge', [
                    'gateway_reference' => $chargeId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
