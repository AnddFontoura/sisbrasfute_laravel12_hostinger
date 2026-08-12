<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerInvitationSendRequest;
use App\Repository\PlayerInvitationRepository;
use App\Service\PlayerInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PlayerInvitationController extends Controller
{
    public function __construct(
        protected PlayerInvitationRepository $playerInvitationRepository,
        protected PlayerInvitationService $playerInvitationService,
    ) {
    }

    public function send(PlayerInvitationSendRequest $request, int $teamId): JsonResponse
    {
        $data = $request->validated();

        $this->playerInvitationService->sendInvitation($data, $teamId);

        return response()->json(['message' => 'Convite enviado com sucesso'], Response::HTTP_CREATED);
    }

    public function index(int $teamId): JsonResponse
    {
        $invitations = $this->playerInvitationRepository->getByTeamPaginated($teamId);

        return response()->json($invitations, Response::HTTP_OK);
    }

    public function cancel(int $teamId, int $invitationId): JsonResponse
    {
        $this->playerInvitationService->cancelInvitation($teamId, $invitationId);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function received(): JsonResponse
    {
        $email = Auth::user()->email;

        $invitations = $this->playerInvitationRepository->getReceivedByEmail($email);

        return response()->json($invitations, Response::HTTP_OK);
    }

    public function accept(int $invitationId): JsonResponse
    {
        $userId = Auth::id();

        $this->playerInvitationService->acceptInvitation($invitationId, $userId);

        return response()->json(['message' => 'Convite aceito com sucesso'], Response::HTTP_OK);
    }
}
