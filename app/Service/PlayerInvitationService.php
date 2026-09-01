<?php

namespace App\Service;

use App\Models\PlayerInvitation;
use App\Models\User;
use App\Repository\PlayerInvitationRepository;
use App\Repository\PlayerRepository;
use App\Repository\TeamPlayerRepository;
use App\Repository\TeamRepository;
use Symfony\Component\HttpFoundation\Response;

class PlayerInvitationService extends BaseService
{
    public function __construct(
        protected PlayerInvitationRepository $playerInvitationRepository,
        protected TeamPlayerRepository $teamPlayerRepository,
        protected PlayerRepository $playerRepository,
        protected NotificationService $notificationService,
        protected TeamRepository $teamRepository,
    ) {}

    public function sendInvitation(array $data, int $teamId): PlayerInvitation
    {
        $email = $data['email'];

        // Check if a pending invitation already exists for the same email+team
        $existingInvitation = $this->playerInvitationRepository->findPendingByEmailAndTeam($email, $teamId);
        throw_if($existingInvitation, new \Exception(
            'Já existe um convite pendente para este email neste time',
            Response::HTTP_CONFLICT
        ));

        // Check if email belongs to a user who already has a TeamPlayer record for this team
        $user = User::where('email', $email)->first();
        if ($user) {
            $existingTeamPlayer = $this->teamPlayerRepository->firstByUserIdAndTeamId($user->id, $teamId);
            throw_if($existingTeamPlayer, new \Exception(
                'Este jogador já faz parte do time',
                Response::HTTP_UNPROCESSABLE_ENTITY
            ));
        }

        // Create the PlayerInvitation record
        return $this->playerInvitationRepository->create([
            'team_id' => $teamId,
            'email' => $email,
            'user_id' => $user?->id,
        ]);
    }

    public function acceptInvitation(int $invitationId, int $userId): void
    {
        // Find the invitation by ID
        $invitation = $this->playerInvitationRepository->firstById($invitationId);
        throw_if(!$invitation, new \Exception(
            'Convite não encontrado',
            Response::HTTP_NOT_FOUND
        ));

        // Check if team_player_id is already set (already fulfilled)
        throw_if($invitation->team_player_id, new \Exception(
            'Este convite já foi aceito',
            Response::HTTP_CONFLICT
        ));

        // Find the user's Player profile
        $player = $this->playerRepository->firstByUserId($userId);
        throw_if(!$player, new \Exception(
            'Você precisa criar um perfil de jogador primeiro',
            Response::HTTP_UNPROCESSABLE_ENTITY
        ));

        // Check if user already has a TeamPlayer for this team
        $existingTeamPlayer = $this->teamPlayerRepository->firstByUserIdAndTeamId($userId, $invitation->team_id);
        throw_if($existingTeamPlayer, new \Exception(
            'Você já faz parte deste time',
            Response::HTTP_CONFLICT
        ));

        // Create a TeamPlayer record with profile data copied from the Player model
        $teamPlayer = $this->teamPlayerRepository->create([
            'team_id' => $invitation->team_id,
            'user_id' => $userId,
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

        // Update the invitation's team_player_id and user_id
        $this->playerInvitationRepository->updateById([
            'team_player_id' => $teamPlayer->id,
            'user_id' => $userId,
        ], $invitationId);

        $team = $this->teamRepository->firstById($invitation->team_id);

        $this->notificationService->notifyUserAcceptedIntoTeam(
            $userId,
            $invitation->team_id,
            $team->name ?? ''
        );
    }

    public function cancelInvitation(int $teamId, int $invitationId): void
    {
        // Find a pending invitation by ID and team (SoftDeletes trait excludes already-deleted records)
        $invitation = $this->playerInvitationRepository->firstById($invitationId);
        throw_if(
            !$invitation || $invitation->team_id !== $teamId,
            new \Exception(
                'Convite não encontrado',
                Response::HTTP_NOT_FOUND
            )
        );

        // Soft-delete the invitation
        $this->playerInvitationRepository->softDeleteById($invitationId);
    }
}
