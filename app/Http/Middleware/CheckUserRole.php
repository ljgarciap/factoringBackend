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
            // Intento manual para ver si el usuario existe
            $manualUser = \App\Models\User::find(4);
            
            \Log::warning('Passport Failure Diagnostic:', [
                'auth_check' => auth('api')->check(),
                'user_4_exists' => $manualUser ? 'YES' : 'NO',
                'db_name' => \DB::getDatabaseName(),
            ]);

            return response()->json([
                'message' => 'Unauthenticated.',
                'debug' => 'Passport fallo. Usuario 4 existe: ' . ($manualUser ? 'SI' : 'NO'),
                'db' => \DB::getDatabaseName(),
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
