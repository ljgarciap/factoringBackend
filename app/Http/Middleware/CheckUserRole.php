<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        \Log::info('Auth Debug:', [
            'has_header' => $request->hasHeader('Authorization'),
            'header' => $request->header('Authorization'),
            'all_headers' => $request->headers->all()
        ]);

        // Autenticación manual usando la guardia api (lo que sabemos que funciona)
        $user = auth('api')->user();

        if (!$user) {
            \Log::warning('Passport Failed to resolve user', [
                'token_present' => $request->hasHeader('Authorization'),
                'guard' => config('auth.guards.api.driver'),
            ]);
            return response()->json([
                'message' => 'Unauthenticated.',
                'debug' => 'Passport no pudo validar el token que llego.'
            ], 401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        $userRoles = $user->roles ?? [];
        if (array_intersect($userRoles, $roles) || in_array('superadmin', $userRoles)) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized role.'], 403);
    }
}
