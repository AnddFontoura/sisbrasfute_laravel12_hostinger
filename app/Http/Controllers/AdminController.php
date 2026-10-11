<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourtRejectRequest;
use App\Models\GamePosition;
use App\Models\Team;
use App\Repository\AdminRepository;
use App\Repository\CourtRepository;
use App\Service\CourtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    public function __construct(
        protected AdminRepository $adminRepository,
        protected CourtService $courtService,
        protected CourtRepository $courtRepository,
    ) {}

    public function users(Request $request): JsonResponse
    {
        $filters = $request->only(['name', 'email', 'email_verified', 'has_player_profile']);
        $perPage = (int) $request->query('per_page', 15);

        $users = $this->adminRepository->getPaginatedUsers($filters, $perPage);

        return response()->json($users, Response::HTTP_OK);
    }

    public function showUser(int $userId): JsonResponse
    {
        $user = $this->adminRepository->getUserWithProfile($userId);

        if (!$user) {
            return response()->json(
                ['error' => __('error.common.resource_not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        return response()->json($user, Response::HTTP_OK);
    }

    public function teams(Request $request): JsonResponse
    {
        $filters = $request->only(['name', 'state_id', 'city_id', 'modality_id']);
        $perPage = (int) $request->query('per_page', 15);

        $teams = $this->adminRepository->getPaginatedTeams($filters, $perPage);

        return response()->json($teams, Response::HTTP_OK);
    }

    public function removeTeamLogo(int $teamId): JsonResponse
    {
        $team = Team::find($teamId);

        if (!$team) {
            return response()->json(
                ['error' => __('error.common.resource_not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$team->logo_path) {
            return response()->json(
                ['error' => __('error.team.logo_not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        Storage::disk('public')->delete($team->logo_path);

        $team->logo_path = null;
        $team->save();

        return response()->json(
            ['message' => __('messages.admin.logo_removed')],
            Response::HTTP_OK
        );
    }

    public function removeTeamBanner(int $teamId): JsonResponse
    {
        $team = Team::find($teamId);

        if (!$team) {
            return response()->json(
                ['error' => __('error.common.resource_not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$team->banner_path) {
            return response()->json(
                ['error' => __('error.team.logo_not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        Storage::disk('public')->delete($team->banner_path);

        $team->banner_path = null;
        $team->save();

        return response()->json(
            ['message' => __('messages.admin.banner_removed')],
            Response::HTTP_OK
        );
    }

    public function matches(Request $request): JsonResponse
    {
        $filters = $request->only(['team_name', 'state_id', 'city_id', 'date_start', 'date_end']);
        $perPage = (int) $request->query('per_page', 15);

        $matches = $this->adminRepository->getPaginatedMatches($filters, $perPage);

        return response()->json($matches, Response::HTTP_OK);
    }

    public function gamePositions(Request $request): JsonResponse
    {
        $query = GamePosition::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('short')) {
            $query->where('short', 'like', '%' . $request->short . '%');
        }

        $perPage = (int) $request->query('per_page', 15);
        $positions = $query->orderBy('name')->paginate($perPage);

        return response()->json($positions, Response::HTTP_OK);
    }

    public function createGamePosition(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'short' => 'required|string|max:10',
            'description' => 'nullable|string|max:10000',
            'icon' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(
                ['errors' => $validator->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $position = GamePosition::create($validator->validated());

        return response()->json($position, Response::HTTP_CREATED);
    }

    public function showGamePosition(int $id): JsonResponse
    {
        $position = GamePosition::find($id);

        if (!$position) {
            return response()->json(
                ['error' => __('error.game_position.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        return response()->json($position, Response::HTTP_OK);
    }

    public function updateGamePosition(Request $request, int $id): JsonResponse
    {
        $position = GamePosition::find($id);

        if (!$position) {
            return response()->json(
                ['error' => __('error.game_position.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'short' => 'required|string|max:10',
            'description' => 'nullable|string|max:10000',
            'icon' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(
                ['errors' => $validator->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $position->update($validator->validated());

        return response()->json($position->fresh(), Response::HTTP_OK);
    }

    public function courts(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'modality_id', 'state_id', 'city_id']);
        $perPage = (int) $request->query('per_page', 15);

        $courts = $this->adminRepository->getPaginatedCourts($filters, $perPage);

        return response()->json($courts, Response::HTTP_OK);
    }

    public function showCourt(int $courtId): JsonResponse
    {
        $court = $this->courtRepository->getDetailForAdmin($courtId);

        if (!$court) {
            return response()->json(
                ['error' => __('error.court.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        return response()->json($court, Response::HTTP_OK);
    }

    public function approveCourt(int $courtId): JsonResponse
    {
        try {
            $this->courtService->approve($courtId);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(__('messages.court.approved'), Response::HTTP_OK);
    }

    public function rejectCourt(CourtRejectRequest $request, int $courtId): JsonResponse
    {
        try {
            $this->courtService->reject($courtId, $request->validated()['reason'] ?? null);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(__('messages.court.rejected'), Response::HTTP_OK);
    }

    public function verifyUserEmail(int $userId): JsonResponse
    {
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json(
                ['error' => __('error.user.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($user->email_verified_at) {
            return response()->json(
                ['message' => __('messages.email_verification.already_verified')],
                Response::HTTP_OK
            );
        }

        $user->email_verified_at = now();
        $user->save();

        return response()->json(
            ['message' => __('messages.email_verification.verified')],
            Response::HTTP_OK
        );
    }
}
