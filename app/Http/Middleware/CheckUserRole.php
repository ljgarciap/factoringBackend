<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated from CheckUserRole.'], 401);
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
