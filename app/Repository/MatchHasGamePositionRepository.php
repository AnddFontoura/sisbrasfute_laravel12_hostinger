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
}