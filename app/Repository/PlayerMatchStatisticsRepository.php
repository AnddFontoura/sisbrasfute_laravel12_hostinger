<?php

namespace App\Repository;

use App\Models\PlayerMatchStatistic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayerMatchStatisticsRepository extends BaseRepository
{
    public function __construct(PlayerMatchStatistic $model)
    {
        $this->model = $model;
    }

    public function findByMatchHasPlayerId(int $matchHasPlayerId): ?PlayerMatchStatistic
    {
        return $this->model
            ->where('match_has_player_id', $matchHasPlayerId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function upsertByMatchHasPlayer(int $matchHasPlayerId, array $data): PlayerMatchStatistic
    {
        return $this->model
            ->updateOrCreate(
                ['match_has_player_id' => $matchHasPlayerId],
                $data
            );
    }

    public function getStatisticsByMatch(int $matchId): Collection
    {
        return DB::table('match_has_players')
            ->join('team_players', 'match_has_players.team_player_id', '=', 'team_players.id')
            ->leftJoin('player_match_statistics', function ($join) {
                $join->on('match_has_players.id', '=', 'player_match_statistics.match_has_player_id')
                    ->whereNull('player_match_statistics.deleted_at');
            })
            ->where('match_has_players.match_id', $matchId)
            ->whereNull('match_has_players.deleted_at')
            ->whereNull('team_players.deleted_at')
            ->select([
                'match_has_players.id as match_has_player_id',
                'team_players.name as player_name',
                'team_players.nickname as player_nickname',
                'player_match_statistics.goals_scored',
                'player_match_statistics.goals_conceded',
                'player_match_statistics.assists',
                'player_match_statistics.yellow_cards',
                'player_match_statistics.red_cards',
                'player_match_statistics.saves',
                'player_match_statistics.fouls_committed',
                'player_match_statistics.fouls_suffered',
            ])
            ->orderBy('team_players.name', 'asc')
            ->get();
    }

    public function getAccumulatedByPlayer(int $teamPlayerId, int $teamId): object
    {
        return DB::table('player_match_statistics')
            ->join('match_has_players', 'player_match_statistics.match_has_player_id', '=', 'match_has_players.id')
            ->join('matches', 'match_has_players.match_id', '=', 'matches.id')
            ->where('match_has_players.team_player_id', $teamPlayerId)
            ->where('matches.created_by_team_id', $teamId)
            ->whereNull('player_match_statistics.deleted_at')
            ->whereNull('match_has_players.deleted_at')
            ->whereNull('matches.deleted_at')
            ->select([
                DB::raw('COALESCE(SUM(player_match_statistics.goals_scored), 0) as goals_scored'),
                DB::raw('COALESCE(SUM(player_match_statistics.goals_conceded), 0) as goals_conceded'),
                DB::raw('COALESCE(SUM(player_match_statistics.assists), 0) as assists'),
                DB::raw('COALESCE(SUM(player_match_statistics.yellow_cards), 0) as yellow_cards'),
                DB::raw('COALESCE(SUM(player_match_statistics.red_cards), 0) as red_cards'),
                DB::raw('COALESCE(SUM(player_match_statistics.saves), 0) as saves'),
                DB::raw('COALESCE(SUM(player_match_statistics.fouls_committed), 0) as fouls_committed'),
                DB::raw('COALESCE(SUM(player_match_statistics.fouls_suffered), 0) as fouls_suffered'),
                DB::raw('COUNT(DISTINCT match_has_players.match_id) as matches_count'),
            ])
            ->first();
    }
}
