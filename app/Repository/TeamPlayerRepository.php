<?php

namespace App\Repository;

use App\Models\TeamPlayer;

class TeamPlayerRepository extends BaseRepository
{
    public function __construct(
        TeamPlayer $model
    ) {
        $this->model = $model;
    }

    public function getPlayersFromTeam(array $filter, int $teamId)
    {
        $sql = $this->model
            ->with(['tags', 'gamePositionInfo'])
            ->where('team_id', $teamId);

        if (isset($filter['showDeleted']) && $filter['showDeleted'] === 'true') {
            $sql->withTrashed();
        }

        // Filtro por nome (busca parcial)
        if (!empty($filter['name'])) {
            $sql->where('name', 'LIKE', '%' . $filter['name'] . '%');
        }

        // Filtro por posição
        if (!empty($filter['game_position_id'])) {
            $sql->where('game_position_id', $filter['game_position_id']);
        }

        // Filtro por tag
        if (!empty($filter['tag_id'])) {
            $sql->whereHas('tags', function ($query) use ($filter) {
                $query->where('team_tags.id', $filter['tag_id']);
            });
        }

        // Filtro por status active
        $activeFilter = $filter['active'] ?? 'true';

        if ($activeFilter === 'all') {
            // Sem filtro — retorna todos os jogadores
        } elseif ($activeFilter === 'false') {
            $sql->where('active', false);
        } else {
            // Default: apenas jogadores ativos
            $sql->where('active', true);
        }

        return $sql->orderBy('name', 'asc')
            ->paginate(12);
    }

    public function firstByUserIdAndTeamId(int $userId, int $teamId)
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->first();
    }

    /**
     * Busca team_player por user_id e team_id.
     * Usado para resolver o team_player_id no fluxo de auto-atribuição de posição.
     */
    public function findByUserAndTeam(int $userId, int $teamId): ?TeamPlayer
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->first();
    }
}
