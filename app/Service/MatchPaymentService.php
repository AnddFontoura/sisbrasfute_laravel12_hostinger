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
     */
    public function processPayment(int $matchId, int $gamePositionId, int $userId): MatchHasPlayer
    {
        [$match, $teamPlayer, $positionValueCents, $feeCents, $totalCost] =
            $this->resolvePositionCost($matchId, $gamePositionId, $userId);

        // Free position: assign immediately, no wallet operations.
        if ($totalCost === 0) {
            return MatchHasPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
                'game_position_id' => $gamePositionId,
                'price_payed' => 0,
                'payment_status' => 'free',
                'payment_method' => PaymentMethod::Wallet->value,
            ]);
        }

        return DB::transaction(function () use ($match, $gamePositionId, $userId, $teamPlayer, $positionValueCents, $feeCents, $totalCost) {
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
                'team_id' => $match->created_by_team_id,
                'description' => "Pagamento posição - Partida #{$match->id}",
                'status' => 'completed',
                'metadata' => ['payment_method' => PaymentMethod::Wallet->value],
            ]);

            $assignment = MatchHasPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
                'game_position_id' => $gamePositionId,
                'price_payed' => $positionValueCents / 100,
                'payment_status' => 'paid',
                'payment_method' => PaymentMethod::Wallet->value,
            ]);

            $this->settleReceivableAndRevenue($match, $positionValueCents, $feeCents, $walletTx->id);

            return $assignment;
        });
    }

    /**
     * Creates an external charge (Pix/boleto) and reserves the position as
     * "pending". The position is only confirmed when the gateway webhook
     * settles the charge (see confirmPositionPayment).
     *
     * @param array<string, mixed> $payer Payer data (name, document, ...) for boleto
     * @return array{assignment: MatchHasPlayer, charge: PaymentChargeResult}
     */
    public function createPendingPositionPayment(
        int $matchId,
        int $gamePositionId,
        int $userId,
        PaymentMethod $method,
        string $returnUrl,
        array $payer = [],
    ): array {
        [$match, $teamPlayer, $positionValueCents, $feeCents, $totalCost] =
            $this->resolvePositionCost($matchId, $gamePositionId, $userId);

        // Free position: no charge needed, assign immediately.
        if ($totalCost === 0) {
            $assignment = MatchHasPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
                'game_position_id' => $gamePositionId,
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

        return DB::transaction(function () use ($match, $gamePositionId, $userId, $teamPlayer, $positionValueCents, $feeCents, $method, $charge) {
            $wallet = $this->walletService->getOrCreateWallet($userId);

            // Reserve the slot as pending.
            $assignment = MatchHasPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
                'game_position_id' => $gamePositionId,
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
                'team_id' => $match->created_by_team_id,
                'description' => "Pagamento posição - Partida #{$match->id}",
                'status' => 'pending',
                'gateway_reference' => $charge->chargeId,
                'metadata' => [
                    'payment_method' => $method->value,
                    'context' => 'match_position',
                    'match_has_player_id' => $assignment->id,
                    'game_position_id' => $gamePositionId,
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

        $teamPlayer = $this->teamPlayerRepository->findByUserAndTeam($userId, $match->created_by_team_id);
        throw_if(!$teamPlayer, new \Exception('Você não é membro do time desta partida', Response::HTTP_FORBIDDEN));

        $assignment = $this->matchHasPlayerRepository->findActiveByMatchAndTeamPlayer($matchId, $teamPlayer->id);
        throw_if(!$assignment, new \Exception('Nenhuma posição encontrada para liberação', Response::HTTP_NOT_FOUND));

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

        DB::transaction(function () use ($match, $userId, $assignment, $positionValueCents) {
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
                'team_id' => $match->created_by_team_id,
                'description' => "Reembolso posição - Partida #{$match->id}",
                'status' => 'completed',
            ]);

            $assignment->delete();

            $receivable = TeamReceivable::where('team_id', $match->created_by_team_id)
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
     * Resolves match, team player and cost breakdown; validates membership.
     *
     * @return array{0: \App\Models\Matches, 1: \App\Models\TeamPlayer, 2: int, 3: int, 4: int}
     */
    private function resolvePositionCost(int $matchId, int $gamePositionId, int $userId): array
    {
        $match = $this->matchesRepository->firstById($matchId);
        throw_if(!$match, new \Exception('Partida não encontrada', Response::HTTP_NOT_FOUND));

        $position = $this->matchHasGamePositionRepository
            ->getPositionsByMatchId($matchId)
            ->where('game_position_id', $gamePositionId)
            ->first();

        throw_if(!$position, new \Exception('Posição inválida', Response::HTTP_UNPROCESSABLE_ENTITY));

        $positionValueCents = (int) (($position->value ?? 0) * 100);
        $feeCents = $positionValueCents > 0 ? $this->calculateFee($positionValueCents) : 0;
        $totalCost = $positionValueCents + $feeCents;

        $teamPlayer = $this->teamPlayerRepository->findByUserAndTeam($userId, $match->created_by_team_id);
        throw_if(!$teamPlayer, new \Exception('Você não é membro do time desta partida', Response::HTTP_FORBIDDEN));

        return [$match, $teamPlayer, $positionValueCents, $feeCents, $totalCost];
    }

    /**
     * Credits the team receivable and records system revenue (fee).
     */
    private function settleReceivableAndRevenue($match, int $positionValueCents, int $feeCents, int $walletTransactionId): void
    {
        TeamReceivable::create([
            'team_id' => $match->created_by_team_id,
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
