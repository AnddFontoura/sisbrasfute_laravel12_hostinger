<?php

namespace App\Http\Middleware;

use App\Models\Matches;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorizes actions that require being the owner (admin) of the team that
 * created the match. Resolves the team from the `teamId` route/input param
 * or, when absent, from the match referenced by `matchId`.
 */
class IsTeamAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $teamId = $request->route('teamId') ?? $request->input('teamId');

        // Fall back to the match's creator team when only matchId is present.
        if (!$teamId && $request->route('matchId')) {
            $match = Matches::find($request->route('matchId'));
            $teamId = $match?->created_by_team_id;
        }

        $team = $teamId ? Team::find($teamId) : null;

        if (!$team || $team->user_id !== Auth::id()) {
            return response()->json(
                ['error' => 'Você não tem permissão para acessar essa página'],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
