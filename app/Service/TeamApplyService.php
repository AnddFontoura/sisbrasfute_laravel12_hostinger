<?php

namespace App\Service;

use App\Repository\PlayerRepository;
use App\Repository\TeamApplyRepository;
use App\Repository\TeamPlayerRepository;
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
        protected TeamPlayerRepository $teamPlayerRepository,
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

    public function answerApplication(array $data, int $teamId, int $teamApplicationId)
    {
       $approved = $data['applicationDecision'] === 'approved';
       $this->teamApplyRepository->updateById(
           [
               'approved' => $approved,
               'rejection_reason' => $data['rejectionReason'] ?? null,
           ],
           $teamApplicationId
       );

        if ($approved) {
            $teamApplication = $this->teamApplyRepository->firstById($teamApplicationId);
            $player = $this->playerRepository->firstById($teamApplication->player_id);

            $this->teamPlayerRepository->create([
                'team_id' => $teamId,
                'user_id' => $player->user_id,
                'birth_city_id' => $player->birth_city_id,
                'game_position_id' => $teamApplication->game_position_id,
                'city_id' => $player->city_id,
                'active' => true,
                'name' => $player->name,
                'nickname' => $player->nickname,
                'uniform_size' => $player->uniform_size,
                'photo' => $player->photo,
                'height' => $player->height,
                'weight' => $player->weight,
                'foot_size' => $player->foot_size,
                'glove_size' => $player->glove_size,
                'birthdate' => $player->birthdate,
            ]);
        }
    }
}