<?php

namespace App\Service;

use App\Enums\MatchType;
use App\Enums\MyTeamIs;
use App\Jobs\SendNewMatchEmailsJob;
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
        protected NotificationService $notificationService,
    ) {

    }

    public function createOrUpdateMatch(array $data, ?int $matchId = null): Matches
    {
        $matchType = MatchType::fromFrontendValue($data['matchType'] ?? 'team_match');
        $myTeamIs = MyTeamIs::fromFrontendValue($data['myTeamIs'] ?? 'home');
        $myTeamInfo = $this->teamRepository->firstById($data['teamId']);

        // Only an intra-team match ("Partida entre o time") splits the squad
        // into several sub-teams. Every other type always has a single list.
        $teamsCount = $matchType === MatchType::Normal
            ? max(2, (int) ($data['teamsCount'] ?? 2))
            : 1;

        $dataToUpdate = [
            'created_by_team_id' => $data['teamId'],
            'match_type' => $matchType->value,
            'my_team_is' => $myTeamIs->value,
            'my_team_id' => $data['teamId'],
            'enemy_team_id' => $data['enemyTeamId'] ?? null,
            'teams_count' => $teamsCount,
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
            'uniform_id' => $data['uniformId'] ?? null,
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
            // E-mail notification: dispatched to RabbitMQ and fanned out on a
            // worker, so creating a match no longer pays for the per-player
            // e-mail loop inside the request. See SendNewMatchEmailsJob.
            SendNewMatchEmailsJob::dispatch($matchInfo->id);

            // In-system notification: team roster + players in the same city
            $this->notificationService->notifyNewMatch($matchInfo->fresh('cityInfo'));
        } else {
            // In-system notification: match a player is involved in was updated
            $this->notificationService->notifyMatchUpdated($matchInfo);
        }

        return $matchInfo;
    }
}
