<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (is_null($request->user()->email_verified_at)) {
            return response()->json([
                'message' => 'Você precisa verificar seu email para acessar essa funcionalidade.',
                'verification_required' => true,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
