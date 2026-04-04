<?php

namespace App\Service;

use App\Repository\MatchesRepository;
use App\Repository\TeamRepository;

class MatchesService extends BaseService
{
    public function __construct(
        protected MatchesRepository $matchesRepository,
        protected TeamRepository $teamRepository,
    ) {

    }

    public function createOrUpdateMatch(array $data, ?int $matchId = null): void
    {
        $isHomeTeam = $data['myTeamIs'] === 'home';
        $myTeamInfo = $this->teamRepository->getById($data['teamId']);

        $dataToUpdate = [
            'created_by_team_id' =>
                $data['teamId'],
            'championship_id' =>
                null,
            'visitor_team_id' =>
                $isHomeTeam ?
                    $data['teamId'] :
                    null,
            'home_team_id' =>
                $isHomeTeam ?
                    $data['teamId'] :
                    null,
            'field_id' =>
                null,
            'city_id' =>
                $data['cityId'],
            'championship_name' =>
                $data['championshipName'] ??
                    null,
            'visitor_team_name' =>
                $isHomeTeam ?
                    $data['enemyTeamName'] :
                    $myTeamInfo->name,
            'visitor_score' =>
                $isHomeTeam ?
                    $data['enemyTeamScore'] :
                    $data['myTeamScore'],
            'visitor_penalty_score' =>
                $isHomeTeam ?
                    $data['enemyTeamPenaltyScore'] :
                    $data['myTeamPenaltyScore'],
            'home_team_name' =>
                $isHomeTeam ?
                    $myTeamInfo->name :
                    $data['enemyTeamName'],
            'home_score' =>
                $isHomeTeam ?
                    $data['myTeamScore'] :
                    $data['enemyTeamScore'],
            'home_penalty_score' =>
                $isHomeTeam ?
                    $data['myTeamPenaltyScore'] :
                    $data['enemyTeamPenaltyScore'],
            'has_penalties' =>
                $data['hasPenalties'] ?? 0,
            'location' =>
                $data['matchLocation'],
            'schedule' =>
                $data['matchSchedule'],
        ];

        if ($matchId) {
            $this->matchesRepository->updateById($dataToUpdate, $matchId);
        } else {
            $this->matchesRepository->create($dataToUpdate);
        }

        //$this->matchHasPlayerService->fillPlayersOnMatch($match, $teamId);
    }
}
