<?php

namespace App\Http\Controllers;

use App\Enums\CourtRole;
use App\Http\Requests\CourtMemberRoleRequest;
use App\Service\CourtTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CourtTeamController extends Controller
{
    public function __construct(
        protected CourtTeamService $courtTeamService,
    ) {
    }

    public function index(int $courtId): JsonResponse
    {
        try {
            $members = $this->courtTeamService->listMembers($courtId, Auth::id());
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(['members' => $members], Response::HTTP_OK);
    }

    public function updateRole(CourtMemberRoleRequest $request, int $courtId, int $userId): JsonResponse
    {
        try {
            $this->courtTeamService->updateMemberRole(
                $courtId,
                $userId,
                CourtRole::from($request->validated()['role'])
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(
            ['message' => __('messages.court_team.role_updated')],
            Response::HTTP_OK
        );
    }

    public function remove(int $courtId, int $userId): JsonResponse
    {
        try {
            $this->courtTeamService->removeMember($courtId, $userId);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
