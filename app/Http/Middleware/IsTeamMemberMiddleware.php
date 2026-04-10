<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\TeamPlayer;
use Closure;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
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
        $teamId = $request->teamId;
        $team = Team::where('id', $teamId)->first();
        $teamHasPlayer = TeamPlayer::where('team_id', $teamId)->where('user_id', Auth::id())->first();

        if (!$teamHasPlayer) {
            return response()->json(
                ['error' => 'Você não tem permissão para acessar essa página'],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);    }
}
