<?php

namespace App\Service;

use App\Contracts\PaymentChargeResult;
use App\Contracts\PaymentGatewayContract;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService extends BaseService
{
    public function __construct(
        protected PaymentGatewayContract $paymentGateway,
    ) {}

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
        $wallet = $this->getOrCreateWallet($userId);

        $chargeResult = $this->paymentGateway->createCharge(
            $amountCents,
            (string) $userId,
            $returnUrl,
            $method,
            $payer,
        );

        WalletTransaction::create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount_cents' => $amountCents,
            'fee_cents' => 0,
            'description' => 'Depósito na carteira',
            'status' => 'pending',
            'gateway_reference' => $chargeResult->chargeId,
            'metadata' => ['payment_method' => $method->value, 'context' => 'deposit'],
        ]);

        return $chargeResult;
    }

    /**
     * Handles a gateway webhook. Routes settlement to the correct destination:
     * wallet deposits credit the balance; match position payments confirm the
     * reserved position (delegated to MatchPaymentService).
     */
    public function handleWebhook(array $payload): void
    {
        $webhookResult = $this->paymentGateway->handleWebhook($payload);

        $transaction = WalletTransaction::where('gateway_reference', $webhookResult->chargeId)
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            return; // Already processed or not found
        }

        // Position payments are settled by the MatchPaymentService.
        if ($transaction->type === 'match_payment') {
            if ($webhookResult->status === PaymentStatus::Completed) {
                app(MatchPaymentService::class)->confirmPositionPayment($webhookResult->chargeId);
            } elseif ($webhookResult->status === PaymentStatus::Failed) {
                app(MatchPaymentService::class)->cancelPendingPositionPayment($webhookResult->chargeId);
            }
            return;
        }

        // Wallet deposit.
        if ($webhookResult->status === PaymentStatus::Completed) {
            DB::transaction(function () use ($transaction) {
                $transaction->update(['status' => 'completed']);
                $wallet = Wallet::find($transaction->wallet_id);
                $wallet->increment('balance_cents', $transaction->amount_cents);
            });
        } elseif ($webhookResult->status === PaymentStatus::Failed) {
            $transaction->update(['status' => 'failed']);
        }
    }

    /**
     * @deprecated Use handleWebhook(). Kept for backward compatibility.
     */
    public function handleDepositWebhook(array $payload): void
    {
        $this->handleWebhook($payload);
    }
}
