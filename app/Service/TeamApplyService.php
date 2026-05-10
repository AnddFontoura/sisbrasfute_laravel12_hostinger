<?php

namespace App\Service;

use App\Repository\PlayerRepository;
use App\Repository\TeamApplyRepository;
use Illuminate\Container\Attributes\Auth;
use Symfony\Component\HttpFoundation\Response;

class TeamApplyService extends BaseService
{
    protected $user;

    public function __construct(
        protected TeamApplyRepository $teamApplyRepository,
        protected TeamService $teamService,
        protected PlayerService $playerService,
        protected PlayerRepository $playerRepository,
    ) {
        $this->user = auth()->user();
    }
    public function checkIfPlayerNotAppliedYet(int $userId, int $teamId)
    {
        $teamApplied = $this->teamApplyRepository->firstByUserIdAndTeamId($userId, $teamId);

        throw_if(isset($teamApplied), new \Exception(
            __('error.team.player_already_applied'),
            Response::HTTP_CONFLICT
        ));
    }

    public function createApplication(array $teamApplyCreateRequest): void
    {
        $teamId = $teamApplyCreateRequest['teamId'];
        $gamePositionId = $teamApplyCreateRequest['gamePositionId'];

        $this->teamService->checkIfTeamExists($teamId);

        $this->playerService->checkIfPlayerExists($this->user->id);

        $playerProfile = $this->playerRepository->firstByUserId($this->user->id);
        $this->checkIfPlayerNotAppliedYet($playerProfile->id, $teamId);


        $this->teamApplyRepository->create([
            'player_id' => $playerProfile->id,
            'team_id' => $teamId,
            'game_position_id' => $gamePositionId,
        ]);
    }
}