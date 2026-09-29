<?php

namespace App\Repository;

use App\Models\Matches;

class MatchesRepository extends BaseRepository
{
    public function __construct(Matches $matches)
    {
        $this->model = $matches;
    }

    public function getOrderedByMatchDate(array $filter, string $orderBy = 'asc')
    {
        $sql = $this->model
            ->with('cityInfo.stateInfo')
            ->orderBy('schedule', $orderBy);

        if (isset($filter['teamId'])) {
            $sql->where('created_by_team_id', $filter['teamId']);
        }

        // Only show active matches in public search
        $sql->where('status', 1);

        return $sql->paginate(12);
    }

    /**
     * Próximas partidas de um time (como criador, mandante ou adversário),
     * agendadas para o futuro, ordenadas da mais próxima para a mais distante.
     * Retorna no máximo $limit registros ativos.
     */
    public function getUpcomingMatchesForTeam(int $teamId, int $limit = 5)
    {
        return $this->model
            ->with('cityInfo.stateInfo')
            ->where('status', 1)
            ->whereNotNull('schedule')
            ->where('schedule', '>=', now())
            ->where(function ($q) use ($teamId) {
                $q->where('created_by_team_id', $teamId)
                  ->orWhere('my_team_id', $teamId)
                  ->orWhere('enemy_team_id', $teamId);
            })
            ->orderBy('schedule', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getById(int $id)
    {
        return $this->model
            ->with(['cityInfo.stateInfo', 'myTeamInfo', 'enemyTeamInfo', 'positions.gamePositionInfo', 'tag', 'uniform'])
            ->where('id', $id)
            ->first();
    }

}
