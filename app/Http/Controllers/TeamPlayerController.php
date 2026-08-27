<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamPlayerCreateOrUpdateRequest;
use App\Http\Requests\TeamPlayerListRequest;
use App\Service\TeamPlayerService;
use App\Service\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TeamPlayerController extends Controller
{
    public function __construct(
        protected TeamPlayerService $teamPlayerService,
        protected UploadService $uploadService,
    ) {

    }

    public function index(TeamPlayerListRequest $request, int $teamId)
    {
        $data = $request->validated();

        $teamPlayers = $this->teamPlayerService->getTeamMembersFromTeam($data, $teamId);

        return response()->json($teamPlayers, JsonResponse::HTTP_OK);
    }

    public function show(int $teamId, int $playerId)
    {
        $teamPlayer = $this->teamPlayerService->getTeamPlayer($teamId, $playerId);

        return response()->json($teamPlayer->load(['tags', 'gamePositionInfo']), JsonResponse::HTTP_OK);
    }

    public function unlink(int $teamId): JsonResponse
    {
        try {
            $this->teamPlayerService->unlinkPlayer($teamId, Auth::id());

            return response()->json(
                ['message' => 'Jogador desvinculado com sucesso.'],
                JsonResponse::HTTP_OK
            );
        } catch (\Exception $e) {
            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
            }

            return response()->json(
                ['message' => $e->getMessage()],
                $statusCode
            );
        }
    }

    public function updateNotificationPreference(Request $request, int $teamId): JsonResponse
    {
        try {
            $notifyMatch = (bool) $request->input('notify_match');

            $this->teamPlayerService->updateNotificationPreference($teamId, Auth::id(), $notifyMatch);

            return response()->json(
                ['message' => 'Preferência de notificação atualizada com sucesso.'],
                JsonResponse::HTTP_OK
            );
        } catch (\Exception $e) {
            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
            }

            return response()->json(
                ['message' => $e->getMessage()],
                $statusCode
            );
        }
    }

    public function toggleActive(Request $request, int $teamId, int $playerId): JsonResponse
    {
        try {
            $active = (bool) $request->input('active');
            $this->teamPlayerService->toggleActive($teamId, $playerId, $active, Auth::id());

            return response()->json(
                ['message' => $active ? 'Jogador ativado com sucesso.' : 'Jogador inativado com sucesso.'],
                JsonResponse::HTTP_OK
            );
        } catch (\Exception $e) {
            $statusCode = $e->getCode();
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
            }
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }

    public function save(TeamPlayerCreateOrUpdateRequest $request, int $teamId, ?int $playerId = null): JsonResponse
    {
        try {
            $data = $request->validated();

            // Remove campos de foto do array de dados (tratados separadamente)
            unset($data['teamPlayerPhoto'], $data['removeTeamPhoto']);

            $teamPlayer = $this->teamPlayerService->saveOrUpdate($data, $teamId, $playerId);

            // Upload da foto do jogador no time
            if ($request->hasFile('teamPlayerPhoto')) {
                // Remove foto anterior se existir
                if ($teamPlayer->photo) {
                    Storage::disk('public')->delete($teamPlayer->photo);
                }

                $path = $this->uploadService->uploadFileToFolder(
                    'public',
                    'player_team_profile',
                    $request->file('teamPlayerPhoto')
                );

                $teamPlayer->photo = $path;
                $teamPlayer->save();
            }

            // Remoção da foto
            if ($request->input('removeTeamPhoto')) {
                if ($teamPlayer->photo) {
                    Storage::disk('public')->delete($teamPlayer->photo);
                    $teamPlayer->photo = null;
                    $teamPlayer->save();
                }
            }

            // Sync tags if provided
            if ($request->has('tag_ids')) {
                $teamPlayer->tags()->sync($request->input('tag_ids', []));
            }

            return response()->json($teamPlayer->load(['tags', 'gamePositionInfo']), JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            $statusCode = $e->getCode();
            // Ensure we have a valid HTTP status code
            if ($statusCode < 100 || $statusCode >= 600) {
                $statusCode = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
            }

            return response()->json(
                ['message' => $e->getMessage()],
                $statusCode
            );
        }
    }
}
