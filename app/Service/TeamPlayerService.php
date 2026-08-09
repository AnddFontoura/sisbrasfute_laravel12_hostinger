<?php

namespace App\Service;

use App\Models\TeamPlayer;
use App\Repository\PlayerRepository;
use App\Repository\TeamPlayerRepository;
use App\Repository\TeamSearchPositionRepository;
use Symfony\Component\HttpFoundation\Response;

class TeamPlayerService extends BaseService
{
    public function __construct(
        protected TeamPlayerRepository $teamPlayerRepository,
        protected TeamSearchPositionService $teamSearchPositionService,
        protected TeamService $teamService,
        protected PlayerService $playerService,
        protected PlayerRepository $playerRepository,
        protected TeamSearchPositionRepository $teamSearchPositionRepository,
    ) {

    }

    public function getTeamMembersFromTeam(array $filter, int $teamId)
    {
        $this->teamService->checkIfTeamExists($teamId);

        return $this->teamPlayerRepository->getPlayersFromTeam($filter, $teamId);
    }

    public function getTeamPlayer(int $teamId, int $playerId): TeamPlayer
    {
        $this->teamService->checkIfTeamExists($teamId);

        $teamPlayer = $this->teamPlayerRepository->firstById($playerId);

        throw_if(
            !$teamPlayer || $teamPlayer->team_id !== $teamId,
            new \Exception('Jogador não encontrado neste time', Response::HTTP_NOT_FOUND)
        );

        return $teamPlayer;
    }

    public function saveOrUpdate(array $data, int $teamId, ?int $playerId = null): TeamPlayer
    {
        $this->teamService->checkIfTeamExists($teamId);

        // Remove tag_ids from data as it's handled separately via sync
        $playerData = collect($data)->except('tag_ids')->toArray();
        $playerData['team_id'] = $teamId;

        if ($playerId) {
            $teamPlayer = $this->teamPlayerRepository->firstById($playerId);

            throw_if(
                !$teamPlayer || $teamPlayer->team_id !== $teamId,
                new \Exception('Jogador não encontrado neste time', Response::HTTP_NOT_FOUND)
            );

            $this->teamPlayerRepository->updateById($playerData, $playerId);
            $teamPlayer = $this->teamPlayerRepository->firstById($playerId);
        } else {
            $teamPlayer = $this->teamPlayerRepository->create($playerData);
        }

        return $teamPlayer;
    }

}
