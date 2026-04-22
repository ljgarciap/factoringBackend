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
        try {
            $user = auth('api')->user();
        } catch (\Exception $e) {
            \Log::error('Passport Exception:', ['msg' => $e->getMessage()]);
            $user = null;
        }

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'debug' => 'Passport no pudo validar el token.',
                'server_time' => now()->toDateTimeString(),
                'token_length' => strlen($request->header('Authorization'))
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
