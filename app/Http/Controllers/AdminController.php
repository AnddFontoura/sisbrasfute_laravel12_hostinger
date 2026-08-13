<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Repository\AdminRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
}
