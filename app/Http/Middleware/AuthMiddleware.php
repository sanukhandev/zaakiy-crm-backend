<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
                $http = Http::timeout(10)->withHeaders([
                    'apikey' => config('services.supabase.api_key'),
                    'Accept' => 'application/json',
                ]);

                if (!app()->environment('production')) {
                    $http = $http->withoutVerifying();
                }

                $response = $http->get(
                    config('services.supabase.url') .
                        '/auth/v1/.well-known/jwks.json',
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

            $supabaseUserId = $decoded->sub ?? null;
            $tenantId =
                $decoded->tenant_id ??
                ($decoded->app_metadata->tenant_id ??
                    $request->header('X-Tenant-Id'));

            if (!$supabaseUserId) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            if (empty($tenantId)) {
                return response()->json(
                    ['message' => 'Tenant missing in token claims'],
                    403,
                );
            }

            $request->attributes->set('auth', [
                'user_id' => $supabaseUserId,
                'tenant_id' => $tenantId,
                'role' => $decoded->role ?? null,
                'email' => $decoded->email ?? null,
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
