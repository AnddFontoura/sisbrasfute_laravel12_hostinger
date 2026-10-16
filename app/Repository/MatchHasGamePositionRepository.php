<?php

namespace App\Repository;

use App\Models\MatchesHasGamePositions;

class MatchHasGamePositionRepository extends BaseRepository
{
    public function __construct(
        MatchesHasGamePositions $model
    ) {
        $this->model = $model;
    }

    /**
     * Busca todas as posições configuradas de uma partida com join para GamePosition.
     * Retorna as posições com o nome da posição de jogo associada.
     */
    public function getPositionsByMatchId(int $matchId)
    {
        return $this->model
            ->where('matches_has_game_positions.match_id', $matchId)
            ->join('game_positions', 'game_positions.id', '=', 'matches_has_game_positions.game_position_id')
            ->select(
                'matches_has_game_positions.id',
                'matches_has_game_positions.match_id',
                'matches_has_game_positions.game_position_id',
                'matches_has_game_positions.team_id',
                'matches_has_game_positions.player_id',
                'matches_has_game_positions.team_reference',
                'matches_has_game_positions.value',
                'game_positions.name as game_position_name'
            )
            ->orderBy('matches_has_game_positions.id', 'asc')
            ->get();
    }

    /**
     * Deleta todas as posições de uma partida pelo match_id.
     */
    public function deleteByMatchId(int $matchId)
    {
        return $this->model
            ->where('match_id', $matchId)
            ->delete();
    }

    /**
     * Deleta as posições de uma partida, exceto os ids informados.
     *
     * Usado no fluxo de edição: os slots que continuam na configuração são
     * reaproveitados (preservando o vínculo com os jogadores em
     * match_has_player.match_has_game_position_id) e apenas os slots que saíram
     * da configuração são removidos.
     *
     * @param array<int, int> $keepIds
     */
    public function deleteByMatchExcludingIds(int $matchId, array $keepIds)
    {
        $query = $this->model->where('match_id', $matchId);

        if (!empty($keepIds)) {
            $query->whereNotIn('id', $keepIds);
        }

        return $query->delete();
    }

    /**
     * Retorna os slots de posição de uma partida, exceto os ids informados.
     * Usado para identificar os slots que serão removidos na edição.
     *
     * @param array<int, int> $keepIds
     */
    public function getByMatchExcludingIds(int $matchId, array $keepIds)
    {
        $query = $this->model->where('match_id', $matchId);

        if (!empty($keepIds)) {
            $query->whereNotIn('id', $keepIds);
        }

        return $query->get();
    }

    /**
     * Retorna as posições de uma partida de um lado específico
     * (time + referência de sub-time).
     */
    public function getByMatchTeamAndReference(int $matchId, int $teamId, int $teamReference)
    {
        return $this->model
            ->where('match_id', $matchId)
            ->where('team_id', $teamId)
            ->where('team_reference', $teamReference)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Retorna as posições de uma partida pertencentes a um time específico.
     */
    public function getByMatchAndTeam(int $matchId, int $teamId)
    {
        return $this->model
            ->where('match_id', $matchId)
            ->where('team_id', $teamId)
            ->get();
    }

    /**
     * Indica se já existem posições da partida para o time informado.
     */
    public function existsForMatchAndTeam(int $matchId, int $teamId): bool
    {
        return $this->model
            ->where('match_id', $matchId)
            ->where('team_id', $teamId)
            ->exists();
    }
}