<?php

namespace App\Http\Controllers;

use App\Service\TeamUniformService;
use App\Service\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeamUniformController extends Controller
{
    public function __construct(
        protected TeamUniformService $teamUniformService,
        protected UploadService $uploadService,
    ) {}

    public function index(int $teamId): JsonResponse
    {
        try {
            $uniforms = $this->teamUniformService->listByTeam($teamId);
            return response()->json($uniforms, JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function store(Request $request, int $teamId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:1|max:100',
            'price_cents' => 'nullable|integer|min:0',
            'uniformPhoto' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:10240',
        ]);

        try {
            $uniform = $this->teamUniformService->create($teamId, $request->only(['name', 'price_cents']));

            if ($request->hasFile('uniformPhoto')) {
                $path = $this->uploadService->uploadFileToFolder(
                    'public',
                    'team_uniforms',
                    $request->file('uniformPhoto')
                );
                $uniform->photo = $path;
                $uniform->save();
            }

            return response()->json($uniform->fresh(), JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function update(Request $request, int $teamId, int $uniformId): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|min:1|max:100',
            'price_cents' => 'nullable|integer|min:0',
            'uniformPhoto' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:10240',
        ]);

        try {
            $uniform = $this->teamUniformService->update(
                $teamId,
                $uniformId,
                $request->only(['name', 'price_cents'])
            );

            if ($request->hasFile('uniformPhoto')) {
                if ($uniform->photo) {
                    Storage::disk('public')->delete($uniform->photo);
                }

                $path = $this->uploadService->uploadFileToFolder(
                    'public',
                    'team_uniforms',
                    $request->file('uniformPhoto')
                );
                $uniform->photo = $path;
                $uniform->save();
            }

            if ($request->input('removePhoto') && !$request->hasFile('uniformPhoto')) {
                if ($uniform->photo) {
                    Storage::disk('public')->delete($uniform->photo);
                    $uniform->photo = null;
                    $uniform->save();
                }
            }

            return response()->json($uniform->fresh(), JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function destroy(int $teamId, int $uniformId): JsonResponse
    {
        try {
            $this->teamUniformService->delete($teamId, $uniformId);
            return response()->json(['success' => __('messages.team.uniform_removed')], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }
}
