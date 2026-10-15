<?php

namespace App\Service;

use App\Enums\CourtInvitationStatus;
use App\Enums\CourtRole;
use App\Mail\CourtTeamInvitationMail;
use App\Models\CourtInvitation;
use App\Models\User;
use App\Repository\CourtInvitationRepository;
use App\Repository\CourtMembershipRepository;
use App\Repository\CourtRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CourtInvitationService extends BaseService
{
    public function __construct(
        protected CourtInvitationRepository $courtInvitationRepository,
        protected CourtMembershipRepository $courtMembershipRepository,
        protected CourtAuthorizationService $courtAuthorizationService,
        protected CourtRepository $courtRepository,
    ) {
    }

    public function sendInvitation(array $data, int $courtId): CourtInvitation
    {
        $this->courtAuthorizationService->assertCanManageTeam($courtId, Auth::id());

        $court = $this->courtRepository->firstById($courtId);
        $email = $data['email'];
        $role = CourtRole::from($data['role']);

        // Reject inviting the court owner (self or current owner's email).
        $ownerEmail = User::where('id', $court->user_id)->value('email');
        throw_if(
            $ownerEmail !== null && $this->emailsMatch($ownerEmail, $email),
            new \Exception(
                __('error.court_invitation.cannot_invite_self'),
                Response::HTTP_UNPROCESSABLE_ENTITY
            )
        );

        // Reject duplicate pending invitation for the same email + court.
        $existing = $this->courtInvitationRepository->findPendingByEmailAndCourt($email, $courtId);
        throw_if(
            $existing,
            new \Exception(
                __('error.court_invitation.duplicate_pending'),
                Response::HTTP_CONFLICT
            )
        );

        // Reject inviting someone who is already an active member of this court.
        $user = User::where('email', $email)->first();
        if ($user) {
            $alreadyMember = $this->courtMembershipRepository->firstActive($courtId, $user->id);
            throw_if(
                $alreadyMember,
                new \Exception(
                    __('error.court_invitation.already_member'),
                    Response::HTTP_CONFLICT
                )
            );
        }

        $invitation = $this->courtInvitationRepository->create([
            'court_id' => $courtId,
            'email' => $email,
            'role' => $role,
            'token' => $this->generateUniqueToken(),
            'status' => CourtInvitationStatus::Pending,
            'invited_by' => Auth::id(),
            'expires_at' => now()->addHours(48),
        ]);

        try {
            Mail::to($email)->send(new CourtTeamInvitationMail($invitation, $court));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar convite de quadra para ' . $email . ': ' . $e->getMessage());
        }

        return $invitation;
    }

    public function acceptInvitation(string $token, int $userId): CourtInvitation
    {
        $invitation = $this->courtInvitationRepository->findByToken($token);

        throw_if(!$invitation, new \Exception(
            __('error.court_invitation.not_found'),
            Response::HTTP_NOT_FOUND
        ));

        $this->assertEmailMatches($invitation, $userId);

        // Treat pending-but-past-expiry as expired (lazy on-read).
        $isExpired = $invitation->status === CourtInvitationStatus::Expired
            || ($invitation->status === CourtInvitationStatus::Pending
                && $invitation->expires_at !== null
                && $invitation->expires_at->isPast());

        throw_if($isExpired, new \Exception(
            __('error.court_invitation.expired'),
            Response::HTTP_CONFLICT
        ));

        throw_if(
            $invitation->status !== CourtInvitationStatus::Pending,
            new \Exception(
                __('error.court_invitation.already_resolved'),
                Response::HTTP_CONFLICT
            )
        );

        $this->courtMembershipRepository->createMembership(
            $invitation->court_id,
            $userId,
            $invitation->role
        );

        // Accepting an invitation unlocks the court feature for this user
        // (idempotent grant of the court-allowed access-gate role).
        $user = User::find($userId);
        if ($user && !$user->hasRole('court-allowed')) {
            $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate([
                'name' => 'court-allowed',
                'guard_name' => 'web',
            ]));
        }

        $this->courtInvitationRepository->updateById([
            'status' => CourtInvitationStatus::Accepted,
            'accepted_at' => now(),
            'accepted_by' => $userId,
        ], $invitation->id);

        return $invitation->fresh();
    }

    public function rejectInvitation(string $token, int $userId): CourtInvitation
    {
        $invitation = $this->courtInvitationRepository->findByToken($token);

        throw_if(!$invitation, new \Exception(
            __('error.court_invitation.not_found'),
            Response::HTTP_NOT_FOUND
        ));

        $this->assertEmailMatches($invitation, $userId);

        throw_if(
            $invitation->status !== CourtInvitationStatus::Pending,
            new \Exception(
                __('error.court_invitation.already_resolved'),
                Response::HTTP_CONFLICT
            )
        );

        $this->courtInvitationRepository->updateById([
            'status' => CourtInvitationStatus::Rejected,
        ], $invitation->id);

        return $invitation->fresh();
    }

    public function cancelInvitation(int $courtId, int $invitationId): void
    {
        $this->courtAuthorizationService->assertCanManageTeam($courtId, Auth::id());

        $invitation = $this->courtInvitationRepository->firstById($invitationId);

        throw_if(
            !$invitation || $invitation->court_id !== $courtId,
            new \Exception(
                __('error.court_invitation.not_found'),
                Response::HTTP_NOT_FOUND
            )
        );

        $this->courtInvitationRepository->softDeleteById($invitationId);
    }

    private function assertEmailMatches(CourtInvitation $invitation, int $userId): void
    {
        $userEmail = User::where('id', $userId)->value('email');

        throw_if(
            $userEmail === null || !$this->emailsMatch($userEmail, $invitation->email),
            new \Exception(
                __('error.court_invitation.email_mismatch'),
                Response::HTTP_FORBIDDEN
            )
        );
    }

    private function emailsMatch(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while ($this->courtInvitationRepository->findByToken($token) !== null);

        return $token;
    }
}
