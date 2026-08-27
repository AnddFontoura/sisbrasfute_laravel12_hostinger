<?php

namespace App\Service;

use App\Models\Team;
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

    public function unlinkPlayer(int $teamId, int $userId): void
    {
        $this->teamService->checkIfTeamExists($teamId);

        $teamPlayer = TeamPlayer::where('team_id', $teamId)
            ->where('user_id', $userId)
            ->first();

        throw_if(
            !$teamPlayer,
            new \Exception('Jogador não encontrado neste time.', Response::HTTP_NOT_FOUND)
        );

        throw_if(
            $teamPlayer->user_id === null,
            new \Exception('Este jogador não está vinculado a nenhuma conta de usuário.', Response::HTTP_BAD_REQUEST)
        );

        throw_if(
            (int) $teamPlayer->user_id !== $userId,
            new \Exception('Você não tem permissão para desvincular este jogador.', Response::HTTP_FORBIDDEN)
        );

        $this->teamPlayerRepository->updateById(['user_id' => null], $teamPlayer->id);
    }

    public function updateNotificationPreference(int $teamId, int $userId, bool $notifyMatch): void
    {
        $this->teamService->checkIfTeamExists($teamId);

        $teamPlayer = TeamPlayer::where('team_id', $teamId)
            ->where('user_id', $userId)
            ->first();

        throw_if(
            !$teamPlayer,
            new \Exception('Jogador não encontrado neste time.', Response::HTTP_NOT_FOUND)
        );

        throw_if(
            $teamPlayer->user_id === null,
            new \Exception('Apenas jogadores vinculados podem alterar preferências.', Response::HTTP_FORBIDDEN)
        );

        $this->teamPlayerRepository->updateById(['notify_match' => $notifyMatch], $teamPlayer->id);
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

    public function toggleActive(int $teamId, int $playerId, bool $active, int $requestingUserId): void
    {
        $this->teamService->checkIfTeamExists($teamId);

        $teamPlayer = $this->teamPlayerRepository->firstById($playerId);

        throw_if(
            !$teamPlayer || $teamPlayer->team_id !== $teamId,
            new \Exception('Jogador não encontrado neste time.', Response::HTTP_NOT_FOUND)
        );

        // Prevent owner from deactivating themselves
        if (!$active && $teamPlayer->user_id !== null) {
            $team = Team::find($teamId);
            throw_if(
                $team && (int) $team->user_id === (int) $teamPlayer->user_id,
                new \Exception('O dono do time não pode ser inativado.', 422)
            );
        }

        $this->teamPlayerRepository->updateById(['active' => $active], $playerId);
    }

}
