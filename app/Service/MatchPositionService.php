<?php

namespace App\Service;

use App\Exceptions\InsufficientBalanceException;
use App\Models\MatchHasPlayer;
use App\Repository\MatchHasGamePositionRepository;
use App\Repository\MatchHasPlayerRepository;
use App\Repository\MatchesRepository;
use App\Repository\TeamPlayerRepository;
use Symfony\Component\HttpFoundation\Response;

class MatchPositionService extends BaseService
{
    public function __construct(
        protected MatchHasPlayerRepository $matchHasPlayerRepository,
        protected MatchHasGamePositionRepository $matchHasGamePositionRepository,
        protected TeamPlayerRepository $teamPlayerRepository,
        protected MatchesRepository $matchesRepository,
        protected MatchPaymentService $matchPaymentService,
        protected MatchNotificationService $matchNotificationService,
        protected NotificationService $notificationService,
    ) {

    }

    /**
     * Retorna lista de posições da partida com dados dos jogadores atribuídos.
     * Para cada posição configurada, distribui jogadores atribuídos sequencialmente
     * entre slots com o mesmo game_position_id.
     */
    public function getPositionsWithPlayers(int $matchId): array
    {
        $match = $this->matchesRepository->firstById($matchId);

        throw_if(!isset($match), new \Exception(
            'Partida não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        $positions = $this->matchHasGamePositionRepository->getPositionsByMatchId($matchId);

        // Get all assignments for this match.
        $allAssignments = MatchHasPlayer::where('match_id', $matchId)
            ->whereNull('deleted_at')
            ->with('teamPlayerInfo')
            ->get();

        // Slot-linked assignments (new model) resolved directly by the
        // position slot id; legacy assignments (no slot link) fall back to
        // sequential matching by game_position_id.
        $bySlot = $allAssignments->whereNotNull('match_has_game_position_id')
            ->keyBy('match_has_game_position_id');
        $legacyByPosition = $allAssignments->whereNull('match_has_game_position_id')
            ->groupBy('game_position_id');

        $result = [];
        $legacyCounters = [];

        foreach ($positions as $position) {
            $assignment = $bySlot->get($position->id);

            // Legacy fallback for assignments created before slot linking.
            if (!$assignment) {
                $gpId = $position->game_position_id;
                $legacyCounters[$gpId] = $legacyCounters[$gpId] ?? 0;
                $legacyForPosition = $legacyByPosition->get($gpId);

                if ($legacyForPosition && $legacyForPosition->count() > $legacyCounters[$gpId]) {
                    $assignment = $legacyForPosition->values()->get($legacyCounters[$gpId]);
                    $legacyCounters[$gpId]++;
                }
            }

            $result[] = [
                'id' => $position->id,
                'match_has_player_id' => $assignment?->id ?? null,
                'game_position_name' => $position->game_position_name,
                'game_position_id' => $position->game_position_id,
                'team_id' => $position->team_id,
                'team_reference' => $position->team_reference,
                'player_name' => $assignment?->teamPlayerInfo?->name ?? null,
                'player_nickname' => $assignment?->teamPlayerInfo?->nickname ?? null,
                'team_player_id' => $assignment?->team_player_id ?? null,
                'value' => (float) ($position->value ?? 0),
                'price_payed' => (float) ($assignment?->price_payed ?? 0),
                'payment_status' => $assignment?->payment_status ?? null,
                'payment_method' => $assignment?->payment_method ?? null,
            ];
        }

        return $result;
    }

    /**
     * Cria ou atualiza atribuição de jogador a uma posição na partida.
     * Valida que o jogador pertence ao time da partida e que a posição está configurada.
     */
    public function savePlayerPosition(int $matchId, array $data): MatchHasPlayer
    {
        $match = $this->matchesRepository->firstById($matchId);

        throw_if(!isset($match), new \Exception(
            'Partida não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        // Validar que a posição está configurada na partida
        $positionConfigured = $this->matchHasGamePositionRepository
            ->getPositionsByMatchId($matchId)
            ->where('game_position_id', $data['game_position_id'])
            ->first();

        throw_if(!isset($positionConfigured), new \Exception(
            'Posição inválida',
            Response::HTTP_UNPROCESSABLE_ENTITY
        ));

        // Validar que o jogador pertence ao time da partida
        $teamPlayer = $this->teamPlayerRepository->firstById($data['team_player_id']);

        throw_if(!isset($teamPlayer) || $teamPlayer->team_id !== $match->created_by_team_id, new \Exception(
            'Jogador inválido',
            Response::HTTP_UNPROCESSABLE_ENTITY
        ));

        // Criar ou atualizar atribuição usando match_id + game_position_id como chave
        $assignment = $this->matchHasPlayerRepository->createOrUpdateByParameters(
            [
                'match_id' => $matchId,
                'game_position_id' => $data['game_position_id'],
            ],
            [
                'team_player_id' => $data['team_player_id'],
                'price_payed' => $data['price_payed'] ?? 0,
            ]
        );

        return $assignment;
    }

    /**
     * Libera a posição do jogador na partida via MatchPaymentService (refund atômico).
     * Resolve a atribuição do usuário em qualquer um dos times da partida.
     */
    public function releasePosition(int $matchId, int $userId): void
    {
        $match = $this->matchesRepository->firstById($matchId);

        throw_if(!isset($match), new \Exception(
            'Partida não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        $assignment = $this->matchHasPlayerRepository->findActiveByMatchAndUser($matchId, $userId);

        throw_if(!isset($assignment), new \Exception(
            'Nenhuma posição encontrada para liberação',
            Response::HTTP_NOT_FOUND
        ));

        // Delegate to MatchPaymentService for atomic refund + deletion
        $this->matchPaymentService->processRefund($matchId, $userId);

        // Notify eligible players about the available position (existing e-mail)
        $this->matchNotificationService->notifyPositionAvailable($match);

        // In-system notification: only players NOT currently on the match list
        $this->notificationService->notifyPositionAvailable($match->fresh());
    }

    /**
     * Auto-atribuição de posição pelo jogador.
     * Valida existência da partida, membership do jogador, configuração da posição,
     * unicidade de atribuição e disponibilidade da posição.
     * Delega a criação e o pagamento ao MatchPaymentService.
     */
    public function selfAssignPosition(
        int $matchId,
        int $matchPositionId,
        int $userId,
        \App\Enums\PaymentMethod $method = \App\Enums\PaymentMethod::Wallet,
        string $returnUrl = '',
        array $payer = [],
    ): array {
        // 1. Validar existência da partida
        $match = $this->matchesRepository->firstById($matchId);

        throw_if(!isset($match), new \Exception(
            'Partida não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        // 2. Validar que a posição (slot) pertence a esta partida
        $slot = $this->matchHasGamePositionRepository
            ->getPositionsByMatchId($matchId)
            ->where('id', $matchPositionId)
            ->first();

        throw_if(!isset($slot), new \Exception(
            'Posição inválida para esta partida',
            Response::HTTP_UNPROCESSABLE_ENTITY
        ));

        // 3. Resolver o jogador no time DONO do slot (criador ou adversário)
        $teamPlayer = $this->teamPlayerRepository->findByUserAndTeam($userId, $slot->team_id);

        throw_if(!isset($teamPlayer), new \Exception(
            'Você não é membro do time desta posição',
            Response::HTTP_FORBIDDEN
        ));

        // 3.5 Validar elegibilidade por tag
        if ($match->tag_id) {
            $tag = \App\Models\TeamTag::find($match->tag_id);

            // Se tag foi deletada, tratar como sem restrição (graceful degradation)
            if ($tag) {
                $hasTag = $teamPlayer->tags()->where('team_tag_id', $match->tag_id)->exists();

                throw_if(!$hasTag, new \Exception(
                    'Você não possui a tag necessária para participar desta partida',
                    Response::HTTP_FORBIDDEN
                ));
            }
        }

        // 4. Validar unicidade: jogador não pode ter mais de uma atribuição ativa na partida
        $existingAssignment = $this->matchHasPlayerRepository->findActiveByMatchAndTeamPlayer($matchId, $teamPlayer->id);

        throw_if(isset($existingAssignment), new \Exception(
            'Você já possui uma posição nesta partida',
            Response::HTTP_UNPROCESSABLE_ENTITY
        ));

        // 5. Validar disponibilidade: este slot específico não pode estar ocupado
        $slotTaken = $this->matchHasPlayerRepository->findActiveByMatchPositionSlot($matchId, $slot->id);

        throw_if(isset($slotTaken), new \Exception(
            'Esta posição já está ocupada',
            Response::HTTP_CONFLICT
        ));

        // 6. Delegate to MatchPaymentService.
        // Wallet: instant debit + confirmed assignment.
        // Pix/boleto: creates a gateway charge and reserves the position as
        //   "pending"; confirmation happens via webhook.
        if ($method->isGateway()) {
            $result = $this->matchPaymentService->createPendingPositionPayment(
                $matchId,
                $slot,
                $teamPlayer,
                $method,
                $returnUrl,
                $payer,
            );

            return [
                'assignment' => $result['assignment'],
                'charge' => $result['charge'],
            ];
        }

        try {
            $assignment = $this->matchPaymentService->processPayment($matchId, $slot, $teamPlayer);
            return ['assignment' => $assignment, 'charge' => null];
        } catch (InsufficientBalanceException $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e);
        }
    }

    /**
     * Atualiza valor de pagamento de uma atribuição existente.
     */
    public function updatePayment(int $matchId, int $atribuicaoId, float $pricePayed): MatchHasPlayer
    {
        $match = $this->matchesRepository->firstById($matchId);

        throw_if(!isset($match), new \Exception(
            'Partida não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        $assignment = $this->matchHasPlayerRepository->findByIdAndMatch($atribuicaoId, $matchId);

        throw_if(!isset($assignment), new \Exception(
            'Atribuição não encontrada para esta partida',
            Response::HTTP_NOT_FOUND
        ));

        $assignment->update(['price_payed' => $pricePayed]);

        return $assignment->fresh();
    }
}
