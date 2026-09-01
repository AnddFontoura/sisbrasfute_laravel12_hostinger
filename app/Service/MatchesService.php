<?php

namespace App\Service;

use App\Enums\MatchType;
use App\Enums\MyTeamIs;
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
        protected MatchNotificationService $matchNotificationService,
        protected NotificationService $notificationService,
    ) {

    }

    public function createOrUpdateMatch(array $data, ?int $matchId = null): Matches
    {
        $matchType = MatchType::fromFrontendValue($data['matchType'] ?? 'team_match');
        $myTeamIs = MyTeamIs::fromFrontendValue($data['myTeamIs'] ?? 'home');
        $myTeamInfo = $this->teamRepository->firstById($data['teamId']);

        $dataToUpdate = [
            'created_by_team_id' => $data['teamId'],
            'match_type' => $matchType->value,
            'my_team_is' => $myTeamIs->value,
            'my_team_id' => $data['teamId'],
            'enemy_team_id' => $data['enemyTeamId'] ?? null,
            'championship_id' => null,
            'field_id' => null,
            'city_id' => $data['cityId'],
            'championship_name' => $data['championshipName'] ?? null,
            'my_team_name' => $myTeamInfo->name,
            'enemy_team_name' => $data['enemyTeamName'] ?? null,
            'my_team_score' => $data['myTeamScore'] ?? null,
            'enemy_team_score' => $data['enemyTeamScore'] ?? null,
            'has_penalties' => $data['hasPenalties'] ?? 0,
            'my_team_penalty_score' => $data['myTeamPenaltyScore'] ?? null,
            'enemy_team_penalty_score' => $data['enemyTeamPenaltyScore'] ?? null,
            'location' => $data['matchLocation'],
            'schedule' => $data['matchSchedule'],
            'tag_id' => $data['tagId'] ?? null,
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

        if (!$matchId) {
            // Existing e-mail notification (kept as-is)
            $this->matchNotificationService->notifyNewMatch($matchInfo);

            // In-system notification: team roster + players in the same city
            $this->notificationService->notifyNewMatch($matchInfo->fresh('cityInfo'));
        } else {
            // In-system notification: match a player is involved in was updated
            $this->notificationService->notifyMatchUpdated($matchInfo);
        }

        return $matchInfo;
    }
}
