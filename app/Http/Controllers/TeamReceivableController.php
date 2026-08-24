<?php

namespace App\Http\Controllers;

use App\Models\TeamReceivable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamReceivableController extends Controller
{
    public function index(int $teamId): JsonResponse
    {
        try {
            $receivables = TeamReceivable::where('team_id', $teamId)
                ->with(['match.myTeamInfo', 'match.enemyTeamInfo'])
                ->orderBy('created_at', 'desc')
                ->get();

            $totalAvailableCents = $receivables
                ->where('status', 'pending')
                ->sum('amount_cents');

            $grouped = $receivables->groupBy('match_id')->map(function ($items, $matchId) {
                $match = $items->first()->match;

                return [
                    'match_id' => $matchId,
                    'match_info' => $match ? [
                        'my_team_name' => $match->my_team_name,
                        'enemy_team_name' => $match->enemy_team_name,
                        'schedule' => $match->schedule,
                        'schedule_br' => $match->schedule_br,
                    ] : null,
                    'total_cents' => $items->sum('amount_cents'),
                    'paid_positions' => $items->count(),
                    'entries' => $items->map(function ($receivable) {
                        return [
                            'id' => $receivable->id,
                            'amount_cents' => $receivable->amount_cents,
                            'status' => $receivable->status,
                            'created_at' => $receivable->created_at,
                        ];
                    })->values(),
                ];
            })->values();

            return response()->json([
                'total_available_cents' => $totalAvailableCents,
                'receivables' => $grouped,
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->getCode() ?: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
