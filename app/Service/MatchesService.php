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
        $isHomeTeam = ($data['myTeamIs'] ?? null) === 'home';
        $isTeamMatch = ($data['matchType'] ?? null) === 'team_match';
        $myTeamInfo = $this->teamRepository->firstById($data['teamId']);

        $dataToUpdate = [
            'created_by_team_id' =>
                $data['teamId'],
            'championship_id' =>
                null,
            'home_team_id' =>
                $isTeamMatch || $isHomeTeam ?
                    $data['teamId'] :
                    null,
            'visitor_team_id' =>
                $isTeamMatch ?
                    $data['teamId'] :
                    ($isHomeTeam ? null : $data['teamId']),
            'field_id' =>
                null,
            'city_id' =>
                $data['cityId'],
            'championship_name' =>
                $data['championshipName'] ??
                    null,
            'visitor_team_name' =>
                $isTeamMatch ?
                    $myTeamInfo->name :
                    ($isHomeTeam ?
                        $data['enemyTeamName'] :
                        $myTeamInfo->name),
            'visitor_score' =>
                $isTeamMatch ?
                    null :
                    ($isHomeTeam ?
                        $data['enemyTeamScore'] :
                        $data['myTeamScore']),
            'visitor_penalty_score' =>
                $isTeamMatch ?
                    null :
                    ($isHomeTeam ?
                        $data['enemyTeamPenaltyScore'] :
                        $data['myTeamPenaltyScore']),
            'home_team_name' =>
                $isTeamMatch ?
                    $myTeamInfo->name :
                    ($isHomeTeam ?
                        $myTeamInfo->name :
                        $data['enemyTeamName']),
            'home_score' =>
                $isTeamMatch ?
                    null :
                    ($isHomeTeam ?
                        $data['myTeamScore'] :
                        $data['enemyTeamScore']),
            'home_penalty_score' =>
                $isTeamMatch ?
                    null :
                    ($isHomeTeam ?
                        $data['myTeamPenaltyScore'] :
                        $data['enemyTeamPenaltyScore']),
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
