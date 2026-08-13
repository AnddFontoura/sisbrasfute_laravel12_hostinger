<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(
                ['error' => 'Acesso restrito a administradores.'],
                Response::HTTP_FORBIDDEN
            );
        }

        if (!$user->is_admin && !$user->hasRole('admin')) {
            return response()->json(
                ['error' => 'Acesso restrito a administradores.'],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
