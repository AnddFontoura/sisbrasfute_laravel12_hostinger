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

        // Get all assignments for this match
        $allAssignments = MatchHasPlayer::where('match_id', $matchId)
            ->whereNull('deleted_at')
            ->with('teamPlayerInfo')
            ->get()
            ->groupBy('game_position_id');

        $result = [];

        // Track how many slots of each game_position_id have been assigned
        $slotCounters = [];

        foreach ($positions as $position) {
            $gpId = $position->game_position_id;

            if (!isset($slotCounters[$gpId])) {
                $slotCounters[$gpId] = 0;
            }

            $assignment = null;
            $assignmentsForPosition = $allAssignments->get($gpId);

            if ($assignmentsForPosition && $assignmentsForPosition->count() > $slotCounters[$gpId]) {
                $assignment = $assignmentsForPosition->values()->get($slotCounters[$gpId]);
            }

            $slotCounters[$gpId]++;

            $result[] = [
                'id' => $position->id,
                'match_has_player_id' => $assignment?->id ?? null,
                'game_position_name' => $position->game_position_name,
                'game_position_id' => $position->game_position_id,
                'player_name' => $assignment?->teamPlayerInfo?->name ?? null,
                'player_nickname' => $assignment?->teamPlayerInfo?->nickname ?? null,
                'team_player_id' => $assignment?->team_player_id ?? null,
                'value' => (float) ($position->value ?? 0),
                'price_payed' => (float) ($assignment?->price_payed ?? 0),
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
     * Resolve team_player_id a partir do user_id + created_by_team_id da partida.
     */
    public function releasePosition(int $matchId, int $userId): void
    {
        $match = $this->matchesRepository->firstById($matchId);

        throw_if(!isset($match), new \Exception(
            'Partida não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        $teamPlayer = $this->teamPlayerRepository->findByUserAndTeam($userId, $match->created_by_team_id);

        throw_if(!isset($teamPlayer), new \Exception(
            'Você não é membro do time desta partida',
            Response::HTTP_FORBIDDEN
        ));

        $assignment = $this->matchHasPlayerRepository->findActiveByMatchAndTeamPlayer($matchId, $teamPlayer->id);

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
    public function selfAssignPosition(int $matchId, int $gamePositionId, int $userId): MatchHasPlayer
    {
        // 1. Validar existência da partida
        $match = $this->matchesRepository->firstById($matchId);

        throw_if(!isset($match), new \Exception(
            'Partida não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        // 2. Resolver team_player_id via user_id + created_by_team_id
        $teamPlayer = $this->teamPlayerRepository->findByUserAndTeam($userId, $match->created_by_team_id);

        throw_if(!isset($teamPlayer), new \Exception(
            'Você não é membro do time desta partida',
            Response::HTTP_FORBIDDEN
        ));

        // 2.5 Validar elegibilidade por tag
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

        // 3. Validar que a posição está configurada na partida
        $positionConfigured = $this->matchHasGamePositionRepository
            ->getPositionsByMatchId($matchId)
            ->where('game_position_id', $gamePositionId)
            ->first();

        throw_if(!isset($positionConfigured), new \Exception(
            'Posição inválida para esta partida',
            Response::HTTP_UNPROCESSABLE_ENTITY
        ));

        // 4. Validar unicidade: jogador não pode ter mais de uma atribuição ativa na partida
        $existingAssignment = $this->matchHasPlayerRepository->findActiveByMatchAndTeamPlayer($matchId, $teamPlayer->id);

        throw_if(isset($existingAssignment), new \Exception(
            'Você já possui uma posição nesta partida',
            Response::HTTP_UNPROCESSABLE_ENTITY
        ));

        // 5. Validar disponibilidade: posição não pode estar ocupada
        $positionTaken = $this->matchHasPlayerRepository->findActiveByMatchAndPosition($matchId, $gamePositionId);

        throw_if(isset($positionTaken), new \Exception(
            'Esta posição já está ocupada',
            Response::HTTP_CONFLICT
        ));

        // 6. Delegate to MatchPaymentService for atomic payment + assignment creation
        try {
            return $this->matchPaymentService->processPayment($matchId, $gamePositionId, $userId);
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
