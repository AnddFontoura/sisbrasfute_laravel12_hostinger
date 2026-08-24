<?php

namespace App\Service;

use App\Contracts\PaymentChargeResult;
use App\Contracts\PaymentGatewayContract;
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

    public function initiateDeposit(int $userId, int $amountCents, string $returnUrl): PaymentChargeResult
    {
        $wallet = $this->getOrCreateWallet($userId);

        $chargeResult = $this->paymentGateway->createCharge(
            $amountCents,
            (string) $userId,
            $returnUrl
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
        ]);

        return $chargeResult;
    }

    public function handleDepositWebhook(array $payload): void
    {
        $webhookResult = $this->paymentGateway->handleWebhook($payload);

        $transaction = WalletTransaction::where('gateway_reference', $webhookResult->chargeId)
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            return; // Already processed or not found
        }

        if ($webhookResult->status === PaymentStatus::Completed) {
            DB::transaction(function () use ($transaction) {
                $transaction->update(['status' => 'completed']);
                $wallet = Wallet::find($transaction->wallet_id);
                $wallet->increment('balance_cents', $transaction->amount_cents);
            });
        } else {
            $transaction->update(['status' => 'failed']);
        }
    }
}
