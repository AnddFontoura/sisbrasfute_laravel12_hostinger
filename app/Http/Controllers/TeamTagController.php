<?php

namespace App\Http\Controllers;

use App\Service\TeamTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamTagController extends Controller
{
    public function __construct(
        protected TeamTagService $teamTagService,
    ) {}

    public function index(int $teamId): JsonResponse
    {
        try {
            $tags = $this->teamTagService->listByTeam($teamId);
            return response()->json($tags, JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function store(Request $request, int $teamId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:1|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        try {
            $tag = $this->teamTagService->create($teamId, $request->only(['name', 'color']));
            return response()->json($tag, JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(Request $request, int $teamId, int $tagId): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|min:1|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        try {
            $tag = $this->teamTagService->update($teamId, $tagId, $request->only(['name', 'color']));
            return response()->json($tag, JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy(int $teamId, int $tagId): JsonResponse
    {
        try {
            $this->teamTagService->delete($teamId, $tagId);
            return response()->json(['success' => 'Tag removida com sucesso'], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
