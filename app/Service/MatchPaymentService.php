<?php

namespace App\Service;

use App\Exceptions\InsufficientBalanceException;
use App\Models\MatchHasPlayer;
use App\Models\Matches;
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

    public function processPayment(int $matchId, int $gamePositionId, int $userId): MatchHasPlayer
    {
        $match = $this->matchesRepository->firstById($matchId);
        throw_if(!$match, new \Exception('Partida não encontrada', Response::HTTP_NOT_FOUND));

        // Get position value
        $position = $this->matchHasGamePositionRepository
            ->getPositionsByMatchId($matchId)
            ->where('game_position_id', $gamePositionId)
            ->first();

        throw_if(!$position, new \Exception('Posição inválida', Response::HTTP_UNPROCESSABLE_ENTITY));

        $positionValueCents = (int) (($position->value ?? 0) * 100); // Convert from BRL to centavos
        $feeCents = $this->calculateFee($positionValueCents);
        $totalCost = $positionValueCents + $feeCents;

        // Resolve team_player_id
        $teamPlayer = $this->teamPlayerRepository->findByUserAndTeam($userId, $match->created_by_team_id);
        throw_if(!$teamPlayer, new \Exception('Você não é membro do time desta partida', Response::HTTP_FORBIDDEN));

        return DB::transaction(function () use ($match, $gamePositionId, $userId, $teamPlayer, $positionValueCents, $feeCents, $totalCost) {
            // 1. Check balance and debit wallet
            $wallet = $this->walletService->getOrCreateWallet($userId);

            throw_if($wallet->balance_cents < $totalCost, new InsufficientBalanceException(
                currentBalance: $wallet->balance_cents,
                requiredAmount: $totalCost,
            ));

            $wallet->decrement('balance_cents', $totalCost);

            // 2. Create wallet transaction
            WalletTransaction::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'match_payment',
                'amount_cents' => $positionValueCents,
                'fee_cents' => $feeCents,
                'match_id' => $match->id,
                'team_id' => $match->created_by_team_id,
                'description' => "Pagamento posição - Partida #{$match->id}",
                'status' => 'completed',
            ]);

            // 3. Create MatchHasPlayer
            $assignment = MatchHasPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
                'game_position_id' => $gamePositionId,
                'price_payed' => $positionValueCents / 100, // Store in BRL for existing field compatibility
            ]);

            // 4. Credit team receivable
            TeamReceivable::create([
                'team_id' => $match->created_by_team_id,
                'match_id' => $match->id,
                'amount_cents' => $positionValueCents,
                'status' => 'pending',
            ]);

            // 5. Record system revenue (fee)
            if ($feeCents > 0) {
                $walletTx = WalletTransaction::where('user_id', $userId)
                    ->where('match_id', $match->id)
                    ->where('type', 'match_payment')
                    ->latest()
                    ->first();

                SystemRevenue::create([
                    'wallet_transaction_id' => $walletTx->id,
                    'amount_cents' => $feeCents,
                    'type' => 'match_fee',
                ]);
            }

            return $assignment;
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

        // Get the position value from the original payment
        $positionValueCents = (int) (($assignment->price_payed ?? 0) * 100);

        DB::transaction(function () use ($match, $userId, $assignment, $positionValueCents) {
            // 1. Credit wallet with position value (NOT fee)
            $wallet = $this->walletService->getOrCreateWallet($userId);
            $wallet->increment('balance_cents', $positionValueCents);

            // 2. Create refund transaction
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

            // 3. Soft-delete MatchHasPlayer
            $assignment->delete();

            // 4. Debit team receivable
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
}
