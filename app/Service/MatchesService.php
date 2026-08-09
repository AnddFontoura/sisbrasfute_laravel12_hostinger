<?php

namespace App\Service;

use App\Models\Matches;
use App\Models\MatchesHasGamePositions;
use App\Repository\MatchesRepository;
use App\Repository\TeamRepository;

class MatchesService extends BaseService
{
    public function __construct(
        protected MatchesRepository $matchesRepository,
        protected MatchesHasGamePositions $matchesHasGamePositions,
        protected TeamRepository $teamRepository,
        protected MatchHasGamePositionService $matchHasGamePositionService,
    ) {

    }

    public function createOrUpdateMatch(array $data, ?int $matchId = null): Matches
    {
        $isHomeTeam = $data['myTeamIs'] === 'home';
        $myTeamInfo = $this->teamRepository->firstById($data['teamId']);

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
            'tag_id' =>
                $data['tagId'] ?? null,
        ];

        if ($matchId) {
            $matchInfo = $this->matchesRepository->updateById($dataToUpdate, $matchId);
        } else {
            $matchInfo = $this->matchesRepository->create($dataToUpdate);
        }

        if (isset($data['positions'])) {
            $positions = is_string($data['positions']) ? json_decode($data['positions'], true) : $data['positions'];
            $data['positions'] = $positions;
            $this->matchHasGamePositionService->createOrUpdateGamePosition($matchInfo, $data);
        }

        return $matchInfo;
    }
}
