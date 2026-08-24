<?php

namespace App\Service;

use App\Models\MatchHasPlayer;
use App\Repository\PlayerMatchStatisticsRepository;
use Illuminate\Validation\ValidationException;

class PlayerMatchStatisticsService extends BaseService
{
    private const STAT_FIELDS = [
        'goals_scored',
        'goals_conceded',
        'assists',
        'yellow_cards',
        'red_cards',
        'saves',
        'fouls_committed',
        'fouls_suffered',
    ];

    public function __construct(
        protected PlayerMatchStatisticsRepository $playerMatchStatisticsRepository,
    ) {

    }

    /**
     * Retorna jogadores escalados com estatísticas para uma partida.
     * Jogadores são ordenados alfabeticamente e valores null viram 0.
     * Inclui linha de totais com soma de cada campo.
     */
    public function getMatchStatistics(int $matchId): array
    {
        $results = $this->playerMatchStatisticsRepository->getStatisticsByMatch($matchId);

        $players = [];
        $totals = array_fill_keys(self::STAT_FIELDS, 0);

        foreach ($results as $row) {
            $statistics = [];
            foreach (self::STAT_FIELDS as $field) {
                $value = (int) ($row->$field ?? 0);
                $statistics[$field] = $value;
                $totals[$field] += $value;
            }

            $players[] = [
                'match_has_player_id' => $row->match_has_player_id,
                'player_name' => $row->player_name,
                'player_nickname' => $row->player_nickname,
                'statistics' => $statistics,
            ];
        }

        return [
            'match_id' => $matchId,
            'players' => $players,
            'totals' => $totals,
        ];
    }

    /**
     * Cria ou atualiza estatísticas em batch para uma partida.
     * Valida que cada match_has_player_id pertence à partida informada.
     *
     * @throws ValidationException
     */
    public function upsertStatistics(int $matchId, array $playersData): void
    {
        $validMatchHasPlayerIds = MatchHasPlayer::where('match_id', $matchId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        foreach ($playersData as $index => $playerData) {
            $matchHasPlayerId = $playerData['match_has_player_id'] ?? null;

            if (!in_array($matchHasPlayerId, $validMatchHasPlayerIds)) {
                throw ValidationException::withMessages([
                    "statistics.{$index}.match_has_player_id" => 'O jogador não está escalado na partida especificada.',
                ]);
            }
        }

        foreach ($playersData as $playerData) {
            $matchHasPlayerId = $playerData['match_has_player_id'];
            $data = array_intersect_key($playerData, array_flip(self::STAT_FIELDS));

            $this->playerMatchStatisticsRepository->upsertByMatchHasPlayer($matchHasPlayerId, $data);
        }
    }

    /**
     * Retorna estatísticas acumuladas de um jogador em um time.
     * Soma total de cada campo + contagem de partidas.
     */
    public function getPlayerAccumulatedStats(int $teamPlayerId, int $teamId): array
    {
        $result = $this->playerMatchStatisticsRepository->getAccumulatedByPlayer($teamPlayerId, $teamId);

        $totals = [];
        foreach (self::STAT_FIELDS as $field) {
            $totals[$field] = (int) ($result->$field ?? 0);
        }

        return [
            'team_player_id' => $teamPlayerId,
            'matches_count' => (int) ($result->matches_count ?? 0),
            'totals' => $totals,
        ];
    }
}
