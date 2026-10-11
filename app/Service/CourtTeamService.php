<?php

namespace App\Service;

use App\Enums\CourtRole;
use App\Repository\CourtMembershipRepository;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CourtTeamService extends BaseService
{
    public function __construct(
        protected CourtMembershipRepository $courtMembershipRepository,
        protected CourtAuthorizationService $courtAuthorizationService,
    ) {
    }

    public function listMembers(int $courtId, int $userId): array
    {
        $this->courtAuthorizationService->assertCanEditData($courtId, $userId);

        return $this->courtMembershipRepository
            ->members($courtId)
            ->map(function ($membership) {
                return [
                    'user_id' => $membership->user_id,
                    'name' => $membership->user?->name,
                    'email' => $membership->user?->email,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ];
            })
            ->values()
            ->all();
    }

    public function updateMemberRole(int $courtId, int $targetUserId, CourtRole $role): void
    {
        $this->courtAuthorizationService->assertCanManageTeam($courtId, Auth::id());

        // Setting the owner role is never allowed through this path.
        throw_if(
            $role === CourtRole::Owner,
            new \Exception(
                __('error.court_invitation.cannot_set_owner'),
                Response::HTTP_UNPROCESSABLE_ENTITY
            )
        );

        $membership = $this->courtMembershipRepository->firstActive($courtId, $targetUserId);

        throw_if(!$membership, new \Exception(
            __('error.court_invitation.not_found'),
            Response::HTTP_NOT_FOUND
        ));

        // The owner row cannot be demoted.
        throw_if(
            $membership->role === CourtRole::Owner,
            new \Exception(
                __('error.court_invitation.cannot_set_owner'),
                Response::HTTP_UNPROCESSABLE_ENTITY
            )
        );

        $this->courtMembershipRepository->updateRole($courtId, $targetUserId, $role);
    }

    public function removeMember(int $courtId, int $targetUserId): void
    {
        $this->courtAuthorizationService->assertCanManageTeam($courtId, Auth::id());

        $membership = $this->courtMembershipRepository->firstActive($courtId, $targetUserId);

        throw_if(!$membership, new \Exception(
            __('error.court_invitation.not_found'),
            Response::HTTP_NOT_FOUND
        ));

        throw_if(
            $membership->role === CourtRole::Owner,
            new \Exception(
                __('error.court_invitation.cannot_remove_owner'),
                Response::HTTP_CONFLICT
            )
        );

        $this->courtMembershipRepository->softDeleteMembership($courtId, $targetUserId);
    }
}
