<?php

namespace App\Http\Controllers;

use App\Models\GamePosition;
use App\Models\Team;
use App\Repository\AdminRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    public function __construct(
        protected AdminRepository $adminRepository,
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
                ['error' => 'Recurso não encontrado.'],
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
                ['error' => 'Recurso não encontrado.'],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$team->logo_path) {
            return response()->json(
                ['error' => 'Imagem não encontrada para este time.'],
                Response::HTTP_NOT_FOUND
            );
        }

        Storage::disk('public')->delete($team->logo_path);

        $team->logo_path = null;
        $team->save();

        return response()->json(
            ['message' => 'Logo removido com sucesso.'],
            Response::HTTP_OK
        );
    }

    public function removeTeamBanner(int $teamId): JsonResponse
    {
        $team = Team::find($teamId);

        if (!$team) {
            return response()->json(
                ['error' => 'Recurso não encontrado.'],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$team->banner_path) {
            return response()->json(
                ['error' => 'Imagem não encontrada para este time.'],
                Response::HTTP_NOT_FOUND
            );
        }

        Storage::disk('public')->delete($team->banner_path);

        $team->banner_path = null;
        $team->save();

        return response()->json(
            ['message' => 'Banner removido com sucesso.'],
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

    public function showGamePosition(int $id): JsonResponse
    {
        $position = GamePosition::find($id);

        if (!$position) {
            return response()->json(
                ['error' => 'Posição não encontrada.'],
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
                ['error' => 'Posição não encontrada.'],
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

    public function verifyUserEmail(int $userId): JsonResponse
    {
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json(
                ['error' => 'Usuário não encontrado.'],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($user->email_verified_at) {
            return response()->json(
                ['message' => 'Email já verificado.'],
                Response::HTTP_OK
            );
        }

        $user->email_verified_at = now();
        $user->save();

        return response()->json(
            ['message' => 'Email verificado com sucesso.'],
            Response::HTTP_OK
        );
    }
}
