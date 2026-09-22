<?php

namespace App\Http\Controllers;

use App\Service\TeamPlayerUniformNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamPlayerUniformNumberController extends Controller
{
    public function __construct(
        protected TeamPlayerUniformNumberService $service,
    ) {}

    /**
     * Lists the team's uniforms with the authenticated player's numbers.
     */
    public function index(int $teamId): JsonResponse
    {
        try {
            $data = $this->service->listForUser($teamId, Auth::id());
            return response()->json($data, JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    /**
     * Replaces the authenticated player's numbers for a given uniform.
     */
    public function update(Request $request, int $teamId, int $uniformId): JsonResponse
    {
        $request->validate([
            'numbers' => 'present|array',
            'numbers.*' => 'integer|min:1|max:999',
        ]);

        try {
            $numbers = $this->service->setNumbers(
                $teamId,
                Auth::id(),
                $uniformId,
                $request->input('numbers', [])
            );

            return response()->json(['numbers' => $numbers], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }
}
