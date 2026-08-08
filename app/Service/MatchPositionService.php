<?php

namespace App\Service;

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
    ) {

    }

    /**
     * Retorna lista de posições da partida com dados dos jogadores atribuídos.
     * Para cada posição configurada, faz busca na MatchHasPlayer para encontrar jogador atribuído.
     */
    public function getPositionsWithPlayers(int $matchId): array
    {
        $match = $this->matchesRepository->firstById($matchId);

        throw_if(!isset($match), new \Exception(
            'Partida não encontrada',
            Response::HTTP_NOT_FOUND
        ));

        $positions = $this->matchHasGamePositionRepository->getPositionsByMatchId($matchId);

        $result = [];

        foreach ($positions as $position) {
            $assignment = $this->matchHasPlayerRepository->findByMatchAndPosition(
                $matchId,
                $position->game_position_id
            );

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
     * Libera a posição do jogador na partida via soft-delete.
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

        $assignment->delete();
    }

    /**
     * Auto-atribuição de posição pelo jogador.
     * Valida existência da partida, membership do jogador, configuração da posição,
     * unicidade de atribuição e disponibilidade da posição.
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

        // 6. Criar atribuição com price_payed = 0
        $assignment = MatchHasPlayer::create([
            'match_id' => $matchId,
            'team_player_id' => $teamPlayer->id,
            'game_position_id' => $gamePositionId,
            'price_payed' => 0,
        ]);

        return $assignment;
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
