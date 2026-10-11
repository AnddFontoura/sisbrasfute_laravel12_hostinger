<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourtInvitationAcceptRequest;
use App\Http\Requests\CourtInvitationSendRequest;
use App\Repository\CourtInvitationRepository;
use App\Service\CourtInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CourtInvitationController extends Controller
{
    public function __construct(
        protected CourtInvitationRepository $courtInvitationRepository,
        protected CourtInvitationService $courtInvitationService,
    ) {
    }

    public function send(CourtInvitationSendRequest $request, int $courtId): JsonResponse
    {
        try {
            $this->courtInvitationService->sendInvitation($request->validated(), $courtId);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(
            ['message' => __('messages.court_invitation.sent')],
            Response::HTTP_CREATED
        );
    }

    public function index(int $courtId): JsonResponse
    {
        try {
            app(\App\Service\CourtAuthorizationService::class)
                ->assertCanManageTeam($courtId, Auth::id());
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        $invitations = $this->courtInvitationRepository->getByCourtPaginated($courtId);

        return response()->json($invitations, Response::HTTP_OK);
    }

    public function cancel(int $courtId, int $invitationId): JsonResponse
    {
        try {
            $this->courtInvitationService->cancelInvitation($courtId, $invitationId);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function received(): JsonResponse
    {
        $invitations = $this->courtInvitationRepository->getReceivedByEmail(Auth::user()->email);

        return response()->json($invitations, Response::HTTP_OK);
    }

    public function accept(CourtInvitationAcceptRequest $request): JsonResponse
    {
        try {
            $this->courtInvitationService->acceptInvitation(
                $request->validated()['token'],
                Auth::id()
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(
            ['message' => __('messages.court_invitation.accepted')],
            Response::HTTP_OK
        );
    }

    public function reject(CourtInvitationAcceptRequest $request): JsonResponse
    {
        try {
            $this->courtInvitationService->rejectInvitation(
                $request->validated()['token'],
                Auth::id()
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(
            ['message' => __('messages.court_invitation.rejected')],
            Response::HTTP_OK
        );
    }
}
