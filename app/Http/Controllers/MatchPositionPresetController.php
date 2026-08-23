<?php

namespace App\Http\Controllers;

use App\Models\MatchPositionPreset;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class MatchPositionPresetController extends Controller
{
    public function index(int $teamId): JsonResponse
    {
        $presets = MatchPositionPreset::where('team_id', $teamId)
            ->orderBy('name')
            ->get();

        return response()->json($presets, Response::HTTP_OK);
    }

    public function store(Request $request, int $teamId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'positions' => 'required|array|min:1',
            'positions.*.game_position_id' => 'required|integer|exists:game_positions,id',
            'positions.*.price' => 'nullable|numeric|min:0',
            'teams_count' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(
                ['errors' => $validator->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $preset = MatchPositionPreset::create([
            'team_id' => $teamId,
            'name' => $request->name,
            'positions' => $request->positions,
            'teams_count' => $request->teams_count ?? 1,
        ]);

        return response()->json($preset, Response::HTTP_CREATED);
    }

    public function show(int $teamId, int $presetId): JsonResponse
    {
        $preset = MatchPositionPreset::where('team_id', $teamId)
            ->where('id', $presetId)
            ->first();

        if (!$preset) {
            return response()->json(
                ['message' => 'Preset não encontrado.'],
                Response::HTTP_NOT_FOUND
            );
        }

        return response()->json($preset, Response::HTTP_OK);
    }

    public function update(Request $request, int $teamId, int $presetId): JsonResponse
    {
        $preset = MatchPositionPreset::where('team_id', $teamId)
            ->where('id', $presetId)
            ->first();

        if (!$preset) {
            return response()->json(
                ['message' => 'Preset não encontrado.'],
                Response::HTTP_NOT_FOUND
            );
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'positions' => 'required|array|min:1',
            'positions.*.game_position_id' => 'required|integer|exists:game_positions,id',
            'positions.*.price' => 'nullable|numeric|min:0',
            'teams_count' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(
                ['errors' => $validator->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $preset->update([
            'name' => $request->name,
            'positions' => $request->positions,
            'teams_count' => $request->teams_count ?? $preset->teams_count,
        ]);

        return response()->json($preset->fresh(), Response::HTTP_OK);
    }

    public function destroy(int $teamId, int $presetId): JsonResponse
    {
        $preset = MatchPositionPreset::where('team_id', $teamId)
            ->where('id', $presetId)
            ->first();

        if (!$preset) {
            return response()->json(
                ['message' => 'Preset não encontrado.'],
                Response::HTTP_NOT_FOUND
            );
        }

        $preset->delete();

        return response()->json(['message' => 'Preset removido com sucesso.'], Response::HTTP_OK);
    }
}
