<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\TeamPlayer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsTeamMemberMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $teamId = $request->route('teamId') ?? $request->input('teamId');

        // If no teamId in route/input, try to resolve from matchId
        if (!$teamId && $request->route('matchId')) {
            $match = \App\Models\Matches::find($request->route('matchId'));
            $teamId = $match?->created_by_team_id;
        }

        if (!$teamId) {
            return response()->json(
                ['error' => 'Você não tem permissão para acessar essa página'],
                Response::HTTP_FORBIDDEN
            );
        }

        $teamHasPlayer = TeamPlayer::where('team_id', $teamId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$teamHasPlayer) {
            return response()->json(
                ['error' => 'Você não tem permissão para acessar essa página'],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
