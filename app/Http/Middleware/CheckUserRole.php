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
            
            // Fallback manual si Passport falla por llaves RSA
            if (!$user && $request->hasHeader('Authorization')) {
                $rawToken = str_replace('Bearer ', '', $request->header('Authorization'));
                $tokenId = json_decode(base64_decode(explode('.', $rawToken)[1] ?? ''), true)['jti'] ?? null;
                
                if ($tokenId) {
                    $tokenRecord = \DB::table('oauth_access_tokens')
                        ->where('id', $tokenId)
                        ->where('revoked', false)
                        ->where('expires_at', '>', now())
                        ->first();
                        
                    if ($tokenRecord) {
                        $user = \App\Models\User::find($tokenRecord->user_id);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Passport Exception:', ['msg' => $e->getMessage()]);
            $user = null;
        }

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'debug' => 'Validacion manual y Passport fallaron.',
                'token_id_detected' => $tokenId ?? 'none'
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
