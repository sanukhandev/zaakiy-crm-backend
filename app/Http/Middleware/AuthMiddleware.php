<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json(['message' => 'No token'], 401);
            }

            // ✅ Cache JWKS for performance
            $jwks = Cache::remember('supabase_jwks', 3600, function () {
                return Http::get(env('SUPABASE_URL') . '/auth/v1/keys')->json();
            });

            $keys = JWK::parseKeySet($jwks);

            $decoded = JWT::decode($token, $keys);

            $userId = $decoded->sub ?? null;

            if (!$userId) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            $user = DB::table('users')
                ->where('id', $userId)
                ->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 401);
            }

            // ✅ Critical validation
            if (!$user->tenant_id) {
                return response()->json([
                    'message' => 'User not linked to tenant'
                ], 403);
            }

            // ✅ Attach auth context
            $request->attributes->set('auth', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'role' => $user->role
            ]);

            return $next($request);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unauthorized',
                'error' => $e->getMessage()
            ], 401);
        }
    }
}