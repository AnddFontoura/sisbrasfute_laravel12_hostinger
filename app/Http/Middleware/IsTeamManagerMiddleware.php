<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsTeamManagerMiddleware
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

        if ($team->user_id != Auth::id()) {
            return response()->json(
                ['error' => 'Você não tem permissão para acessar essa página'],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
