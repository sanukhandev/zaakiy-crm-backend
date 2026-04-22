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

            $jwks = Cache::remember('supabase_jwks', 3600, function () {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'apikey' => env('SUPABASE_ANON_KEY'),
                        'Accept' => 'application/json',
                    ])
                    ->get(
                        env('SUPABASE_URL') . '/auth/v1/.well-known/jwks.json',
                    );

                if (!$response->successful()) {
                    throw new \Exception(
                        'Failed to fetch JWKS: ' . $response->body(),
                    );
                }

                $json = $response->json();

                if (!isset($json['keys'])) {
                    throw new \Exception(
                        'Invalid JWKS response: ' . json_encode($json),
                    );
                }

                return $json;
            });

            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $keys);

            $userId = $decoded->sub ?? null;

            if (!$userId) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            $user = DB::table('users')->where('id', $userId)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 401);
            }

            if (!$user->tenant_id) {
                return response()->json(
                    ['message' => 'User not linked to tenant'],
                    403,
                );
            }

            $request->attributes->set('auth', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'role' => $user->role,
            ]);

            return $next($request);
        } catch (\Throwable $e) {
            return response()->json(
                [
                    'message' => 'Unauthorized',
                    'error' => $e->getMessage(),
                ],
                401,
            );
        }
    }
}
