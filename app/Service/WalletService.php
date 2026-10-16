<?php

namespace App\Service;

use App\Contracts\PaymentChargeResult;
use App\Contracts\PaymentGatewayContract;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentMethodNotAvailableException;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService extends BaseService
{
    public function __construct(
        protected PaymentGatewayContract $paymentGateway,
        protected SystemConfigService $systemConfigService,
    ) {}

    /**
     * Ensures a gateway payment method is enabled on the active gateway before
     * a charge is created. Instant methods (wallet) never hit a gateway and are
     * therefore always allowed.
     */
    public function assertMethodAvailable(PaymentMethod $method): void
    {
        if (!$method->isGateway()) {
            return;
        }

        if (!$this->systemConfigService->isMethodEnabled($method->value)) {
            throw new PaymentMethodNotAvailableException(
                $method->value,
                $this->systemConfigService->getPaymentGateway(),
            );
        }
    }

    public function getOrCreateWallet(int $userId): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance_cents' => 0]
        );
    }

    public function getBalance(int $userId): int
    {
        $wallet = $this->getOrCreateWallet($userId);
        return $wallet->balance_cents;
    }

    public function credit(int $userId, int $amountCents, string $description): WalletTransaction
    {
        $wallet = $this->getOrCreateWallet($userId);

        $wallet->increment('balance_cents', $amountCents);

        return WalletTransaction::create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'type' => 'refund',
            'amount_cents' => $amountCents,
            'fee_cents' => 0,
            'description' => $description,
            'status' => 'completed',
        ]);
    }

    public function debit(int $userId, int $amountCents, string $description): WalletTransaction
    {
        $wallet = $this->getOrCreateWallet($userId);

        throw_if($wallet->balance_cents < $amountCents, new \App\Exceptions\InsufficientBalanceException(
            currentBalance: $wallet->balance_cents,
            requiredAmount: $amountCents,
        ));

        $wallet->decrement('balance_cents', $amountCents);

        return WalletTransaction::create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'type' => 'match_payment',
            'amount_cents' => $amountCents,
            'fee_cents' => 0,
            'description' => $description,
            'status' => 'completed',
        ]);
    }

    /**
     * @param array<string, mixed> $payer Payer data (name, document, ...) for boleto
     */
    public function initiateDeposit(
        int $userId,
        int $amountCents,
        string $returnUrl,
        PaymentMethod $method = PaymentMethod::Pix,
        array $payer = [],
    ): PaymentChargeResult {
        $this->assertMethodAvailable($method);

        $wallet = $this->getOrCreateWallet($userId);

        $chargeResult = $this->paymentGateway->createCharge(
            $amountCents,
            (string) $userId,
            $returnUrl,
            $method,
            $payer,
        );

        // Synchronous methods (credit card) are authorized in-line, so the
        // charge may already be completed. Persist the final status and credit
        // the balance immediately instead of waiting for a webhook that would
        // never arrive as "pending".
        $alreadyCompleted = $method->isSynchronous()
            && $chargeResult->status === PaymentStatus::Completed->value;

        $transaction = WalletTransaction::create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount_cents' => $amountCents,
            'fee_cents' => 0,
            'description' => 'Depósito na carteira',
            'status' => $alreadyCompleted ? 'completed' : 'pending',
            'gateway_reference' => $chargeResult->chargeId,
            'metadata' => ['payment_method' => $method->value, 'context' => 'deposit'],
        ]);

        if ($alreadyCompleted) {
            $wallet->increment('balance_cents', $amountCents);
        }

        return $chargeResult;
    }

    /**
     * Handles a gateway webhook. Routes settlement to the correct destination:
     * wallet deposits credit the balance; match position payments confirm the
     * reserved position (delegated to MatchPaymentService).
     *
     * SECURITY — the webhook endpoint is public and unauthenticated, so the
     * payload is NEVER trusted to decide money movement. We take only the
     * charge reference (txid) from it, then independently re-query the gateway
     * for the authoritative status via checkStatus(). A forged POST can at most
     * trigger a status re-check against a txid; it cannot credit a balance
     * because the gateway must itself confirm the charge is settled.
     */
    public function handleWebhook(array $payload): void
    {
        $parsed = $this->paymentGateway->handleWebhook($payload);
        $chargeId = $parsed->chargeId;

        if ($chargeId === '' || $chargeId === 'unknown') {
            return; // Nothing actionable in the payload.
        }

        $this->settleCharge($chargeId);
    }

    /**
     * Settles a charge by its gateway reference using the AUTHORITATIVE status
     * fetched from the gateway (not the webhook payload). Shared by the webhook
     * worker and the reconciliation job, so both paths verify the same way and
     * are idempotent.
     */
    public function settleCharge(string $chargeId): void
    {
        $type = WalletTransaction::where('gateway_reference', $chargeId)->value('type');

        if ($type === null) {
            return; // No transaction for this reference.
        }

        // Authoritative status straight from the gateway (verifies the txid).
        $status = $this->paymentGateway->checkStatus($chargeId);

        if ($status === PaymentStatus::Pending) {
            return; // Not settled yet; a later webhook/reconcile run will catch it.
        }

        // Position payments are settled by the MatchPaymentService, which owns
        // its own transactional locking around the match position.
        if ($type === 'match_payment') {
            if ($status === PaymentStatus::Completed) {
                app(MatchPaymentService::class)->confirmPositionPayment($chargeId);
            } elseif ($status === PaymentStatus::Failed || $status === PaymentStatus::Expired) {
                app(MatchPaymentService::class)->cancelPendingPositionPayment($chargeId);
            }
            return;
        }

        // Wallet deposit. Lock the pending transaction row INSIDE the
        // transaction before deciding to credit. This closes the race where
        // two concurrent re-deliveries both read "pending" and both credit:
        // the second one blocks on the lock, then sees status != pending and
        // becomes a no-op. Combined with the unique index on
        // gateway_reference, double-crediting is impossible.
        DB::transaction(function () use ($chargeId, $status) {
            $transaction = WalletTransaction::where('gateway_reference', $chargeId)
                ->lockForUpdate()
                ->first();

            if (!$transaction || $transaction->status !== 'pending') {
                return; // Already processed, failed, or not found.
            }

            if ($status === PaymentStatus::Completed) {
                $transaction->update(['status' => 'completed']);
                Wallet::whereKey($transaction->wallet_id)
                    ->increment('balance_cents', $transaction->amount_cents);
            } elseif ($status === PaymentStatus::Failed || $status === PaymentStatus::Expired) {
                $transaction->update(['status' => 'failed']);
            }
        });
    }

    /**
     * @deprecated Use handleWebhook(). Kept for backward compatibility.
     */
    public function handleDepositWebhook(array $payload): void
    {
        $this->handleWebhook($payload);
    }
}
