<?php

namespace App\Service;

use App\Contracts\PaymentChargeResult;
use App\Contracts\PaymentGatewayContract;
use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientBalanceException;
use App\Models\MatchHasPlayer;
use App\Models\SystemRevenue;
use App\Models\TeamReceivable;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Repository\MatchesRepository;
use App\Repository\MatchHasGamePositionRepository;
use App\Repository\MatchHasPlayerRepository;
use App\Repository\TeamPlayerRepository;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MatchPaymentService extends BaseService
{
    public function __construct(
        protected WalletService $walletService,
        protected SystemConfigService $systemConfigService,
        protected MatchesRepository $matchesRepository,
        protected MatchHasGamePositionRepository $matchHasGamePositionRepository,
        protected MatchHasPlayerRepository $matchHasPlayerRepository,
        protected TeamPlayerRepository $teamPlayerRepository,
        protected PaymentGatewayContract $paymentGateway,
    ) {}

    public function calculateFee(int $positionValueCents): int
    {
        $feeType = $this->systemConfigService->getFeeType();
        $feeValue = $this->systemConfigService->getFeeValue();

        if ($feeType === 'percentage') {
            // feeValue is in basis points (e.g., 500 = 5%)
            return (int) ceil($positionValueCents * $feeValue / 10000);
        }

        // Fixed fee
        return $feeValue;
    }

    /**
     * Processes a position payment using the wallet balance (instant).
     *
     * @param object $slot The position slot (matches_has_game_positions row)
     * @param object $teamPlayer The team player being assigned
     */
    public function processPayment(int $matchId, object $slot, object $teamPlayer): MatchHasPlayer
    {
        $match = $this->matchesRepository->firstById($matchId);
        throw_if(!$match, new \Exception('Partida não encontrada', Response::HTTP_NOT_FOUND));

        [$positionValueCents, $feeCents, $totalCost] = $this->costForSlot($slot);
        $userId = $teamPlayer->user_id;

        // Free position: assign immediately, no wallet operations.
        if ($totalCost === 0) {
            return MatchHasPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
                'game_position_id' => $slot->game_position_id,
                'match_has_game_position_id' => $slot->id,
                'price_payed' => 0,
                'payment_status' => 'free',
                'payment_method' => PaymentMethod::Wallet->value,
            ]);
        }

        return DB::transaction(function () use ($match, $slot, $teamPlayer, $userId, $positionValueCents, $feeCents, $totalCost) {
            $wallet = $this->walletService->getOrCreateWallet($userId);

            throw_if($wallet->balance_cents < $totalCost, new InsufficientBalanceException(
                currentBalance: $wallet->balance_cents,
                requiredAmount: $totalCost,
            ));

            $wallet->decrement('balance_cents', $totalCost);

            $walletTx = WalletTransaction::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'match_payment',
                'amount_cents' => $positionValueCents,
                'fee_cents' => $feeCents,
                'match_id' => $match->id,
                'team_id' => $slot->team_id,
                'description' => "Pagamento posição - Partida #{$match->id}",
                'status' => 'completed',
                'metadata' => ['payment_method' => PaymentMethod::Wallet->value],
            ]);

            $assignment = MatchHasPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
                'game_position_id' => $slot->game_position_id,
                'match_has_game_position_id' => $slot->id,
                'price_payed' => $positionValueCents / 100,
                'payment_status' => 'paid',
                'payment_method' => PaymentMethod::Wallet->value,
            ]);

            $this->settleReceivableAndRevenue($match, $slot->team_id, $positionValueCents, $feeCents, $walletTx->id);

            return $assignment;
        });
    }

    /**
     * Creates an external charge (Pix/boleto) and reserves the position as
     * "pending". The position is only confirmed when the gateway webhook
     * settles the charge (see confirmPositionPayment).
     *
     * @param object $slot The position slot (matches_has_game_positions row)
     * @param object $teamPlayer The team player being assigned
     * @param array<string, mixed> $payer Payer data (name, document, ...) for boleto
     * @return array{assignment: MatchHasPlayer, charge: PaymentChargeResult}
     */
    public function createPendingPositionPayment(
        int $matchId,
        object $slot,
        object $teamPlayer,
        PaymentMethod $method,
        string $returnUrl,
        array $payer = [],
    ): array {
        $match = $this->matchesRepository->firstById($matchId);
        throw_if(!$match, new \Exception('Partida não encontrada', Response::HTTP_NOT_FOUND));

        [$positionValueCents, $feeCents, $totalCost] = $this->costForSlot($slot);
        $userId = $teamPlayer->user_id;

        // Free position: no charge needed, assign immediately.
        if ($totalCost === 0) {
            $assignment = MatchHasPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
                'game_position_id' => $slot->game_position_id,
                'match_has_game_position_id' => $slot->id,
                'price_payed' => 0,
                'payment_status' => 'free',
                'payment_method' => $method->value,
            ]);

            return [
                'assignment' => $assignment,
                'charge' => new PaymentChargeResult(
                    chargeId: '',
                    paymentUrl: $returnUrl,
                    status: 'free',
                    method: $method->value,
                ),
            ];
        }

        $charge = $this->paymentGateway->createCharge(
            $totalCost,
            (string) $userId,
            $returnUrl,
            $method,
            $payer,
        );

        return DB::transaction(function () use ($match, $slot, $teamPlayer, $userId, $positionValueCents, $feeCents, $method, $charge) {
            $wallet = $this->walletService->getOrCreateWallet($userId);

            // Reserve the slot as pending.
            $assignment = MatchHasPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
                'game_position_id' => $slot->game_position_id,
                'match_has_game_position_id' => $slot->id,
                'price_payed' => $positionValueCents / 100,
                'payment_status' => 'pending',
                'payment_reference' => $charge->chargeId,
                'payment_method' => $method->value,
            ]);

            // Pending transaction linked to the charge; settled by webhook.
            WalletTransaction::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'match_payment',
                'amount_cents' => $positionValueCents,
                'fee_cents' => $feeCents,
                'match_id' => $match->id,
                'team_id' => $slot->team_id,
                'description' => "Pagamento posição - Partida #{$match->id}",
                'status' => 'pending',
                'gateway_reference' => $charge->chargeId,
                'metadata' => [
                    'payment_method' => $method->value,
                    'context' => 'match_position',
                    'match_has_player_id' => $assignment->id,
                    'game_position_id' => $slot->game_position_id,
                    'team_id' => $slot->team_id,
                ],
            ]);

            return ['assignment' => $assignment, 'charge' => $charge];
        });
    }

    /**
     * Confirms a pending position payment identified by the gateway charge id.
     * Called from the payment webhook. Idempotent.
     */
    public function confirmPositionPayment(string $chargeId): void
    {
        $transaction = WalletTransaction::where('gateway_reference', $chargeId)
            ->where('type', 'match_payment')
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            return; // Not found or already settled.
        }

        DB::transaction(function () use ($transaction) {
            $transaction->update(['status' => 'completed']);

            $assignment = MatchHasPlayer::where('payment_reference', $transaction->gateway_reference)
                ->where('payment_status', 'pending')
                ->first();

            if ($assignment) {
                $assignment->update(['payment_status' => 'paid']);
            }

            $match = $this->matchesRepository->firstById($transaction->match_id);
            if ($match) {
                $this->settleReceivableAndRevenue(
                    $match,
                    $transaction->team_id ?? $match->created_by_team_id,
                    $transaction->amount_cents,
                    $transaction->fee_cents,
                    $transaction->id,
                );
            }
        });
    }

    /**
     * Cancels a pending position payment (e.g., charge expired/failed).
     * Releases the reserved slot. Idempotent.
     */
    public function cancelPendingPositionPayment(string $chargeId): void
    {
        $transaction = WalletTransaction::where('gateway_reference', $chargeId)
            ->where('type', 'match_payment')
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            return;
        }

        DB::transaction(function () use ($transaction) {
            $transaction->update(['status' => 'failed']);

            MatchHasPlayer::where('payment_reference', $transaction->gateway_reference)
                ->where('payment_status', 'pending')
                ->delete();
        });
    }

    public function processRefund(int $matchId, int $userId): void
    {
        $match = $this->matchesRepository->firstById($matchId);
        throw_if(!$match, new \Exception('Partida não encontrada', Response::HTTP_NOT_FOUND));

        // Resolve the user's active assignment across whichever team they belong to.
        $assignment = $this->matchHasPlayerRepository->findActiveByMatchAndUser($matchId, $userId);
        throw_if(!$assignment, new \Exception('Nenhuma posição encontrada para liberação', Response::HTTP_NOT_FOUND));

        // Team that owns the position (creator or opponent side).
        $slotTeamId = $assignment->matchPositionSlot?->team_id ?? $match->created_by_team_id;

        // A pending (unpaid) position: just release the reservation, no refund.
        if ($assignment->payment_status === 'pending') {
            DB::transaction(function () use ($assignment) {
                WalletTransaction::where('gateway_reference', $assignment->payment_reference)
                    ->where('status', 'pending')
                    ->update(['status' => 'failed']);
                $assignment->delete();
            });
            return;
        }

        $positionValueCents = (int) (($assignment->price_payed ?? 0) * 100);

        DB::transaction(function () use ($match, $userId, $assignment, $slotTeamId, $positionValueCents) {
            // Refund always goes to the wallet, regardless of original method.
            $wallet = $this->walletService->getOrCreateWallet($userId);
            $wallet->increment('balance_cents', $positionValueCents);

            WalletTransaction::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'refund',
                'amount_cents' => $positionValueCents,
                'fee_cents' => 0,
                'match_id' => $match->id,
                'team_id' => $slotTeamId,
                'description' => "Reembolso posição - Partida #{$match->id}",
                'status' => 'completed',
            ]);

            $assignment->delete();

            $receivable = TeamReceivable::where('team_id', $slotTeamId)
                ->where('match_id', $match->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($receivable) {
                $receivable->update([
                    'amount_cents' => max(0, $receivable->amount_cents - $positionValueCents),
                ]);
            }
        });
    }

    /**
     * Cost breakdown for a position slot.
     *
     * @return array{0: int, 1: int, 2: int} [positionValueCents, feeCents, totalCost]
     */
    private function costForSlot(object $slot): array
    {
        $positionValueCents = (int) (($slot->value ?? 0) * 100);
        $feeCents = $positionValueCents > 0 ? $this->calculateFee($positionValueCents) : 0;
        $totalCost = $positionValueCents + $feeCents;

        return [$positionValueCents, $feeCents, $totalCost];
    }

    /**
     * Credits the team receivable and records system revenue (fee).
     */
    private function settleReceivableAndRevenue($match, int $teamId, int $positionValueCents, int $feeCents, int $walletTransactionId): void
    {
        TeamReceivable::create([
            'team_id' => $teamId,
            'match_id' => $match->id,
            'amount_cents' => $positionValueCents,
            'status' => 'pending',
        ]);

        if ($feeCents > 0) {
            SystemRevenue::create([
                'wallet_transaction_id' => $walletTransactionId,
                'amount_cents' => $feeCents,
                'type' => 'match_fee',
            ]);
        }
    }
}
