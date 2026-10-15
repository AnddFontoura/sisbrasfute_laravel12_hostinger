<?php

namespace App\Service;

use App\Enums\CourtRole;
use App\Repository\CourtMembershipRepository;
use App\Repository\CourtRepository;
use Symfony\Component\HttpFoundation\Response;

class CourtAuthorizationService extends BaseService
{
    public function __construct(
        protected CourtMembershipRepository $courtMembershipRepository,
        protected CourtRepository $courtRepository
    ) {
    }

    public function roleFor(int $courtId, int $userId): ?CourtRole
    {
        return $this->courtMembershipRepository->roleFor($courtId, $userId);
    }

    public function assertCanEditData(int $courtId, int $userId): void
    {
        $this->assertCourtExists($courtId);

        $role = $this->roleFor($courtId, $userId);

        throw_if(
            $role === null || !$role->canEditData(),
            new \Exception(
                __('error.court_invitation.forbidden'),
                Response::HTTP_FORBIDDEN
            )
        );
    }

    public function assertCanManageTeam(int $courtId, int $userId): void
    {
        $this->assertCourtExists($courtId);

        $role = $this->roleFor($courtId, $userId);

        throw_if(
            $role === null || !$role->canManageTeam(),
            new \Exception(
                __('error.court_invitation.forbidden'),
                Response::HTTP_FORBIDDEN
            )
        );
    }

    public function assertCanManageReservations(int $courtId, int $userId): void
    {
        $this->assertCourtExists($courtId);

        $role = $this->roleFor($courtId, $userId);

        throw_if(
            $role === null || !$role->canManageReservations(),
            new \Exception(
                __('error.court_reservation.forbidden'),
                Response::HTTP_FORBIDDEN
            )
        );
    }

    private function assertCourtExists(int $courtId): void
    {
        $court = $this->courtRepository->firstById($courtId);

        throw_if(!$court, new \Exception(
            __('error.court.not_found'),
            Response::HTTP_NOT_FOUND
        ));
    }
}
