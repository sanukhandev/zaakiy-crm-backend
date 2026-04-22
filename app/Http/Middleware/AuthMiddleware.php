<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
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
            $jwtEmail = $decoded->email ?? null;

            if (!$supabaseUserId) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            $userContext = Cache::remember(
                'auth_user_ctx_' . $supabaseUserId,
                300,
                function () use ($supabaseUserId, $jwtEmail) {
                    try {
                        if (!Schema::hasTable('users')) {
                            return null;
                        }

                        $user = null;

                        if (Schema::hasColumn('users', 'supabase_user_id')) {
                            $user = DB::table('users')
                                ->where('supabase_user_id', $supabaseUserId)
                                ->first();
                        }

                        if (!$user) {
                            $user = DB::table('users')
                                ->where('id', $supabaseUserId)
                                ->first();
                        }

                        if (!$user && $jwtEmail) {
                            $user = DB::table('users')
                                ->where('email', $jwtEmail)
                                ->first();
                        }

                        if (!$user) {
                            return null;
                        }

                        return [
                            'id' => $user->id ?? null,
                            'tenant_id' => $user->tenant_id ?? null,
                            'role' => $user->role ?? null,
                        ];
                    } catch (\Throwable) {
                        // If user lookup fails, middleware continues with token/header fallbacks.
                        return null;
                    }
                },
            );

            $tenantId =
                $userContext['tenant_id'] ??
                ($decoded->tenant_id ??
                    ($decoded->tenantId ??
                        ($decoded->org_id ??
                            ($decoded->app_metadata->tenant_id ??
                                ($decoded->app_metadata->tenantId ??
                                    ($request->header('X-Tenant-Id') ??
                                        config(
                                            'services.supabase.default_tenant_id',
                                        )))))));

            if (empty($tenantId)) {
                return response()->json(
                    [
                        'message' =>
                            'Tenant context could not be resolved. Provide tenant_id claim, X-Tenant-Id header, or SUPABASE_DEFAULT_TENANT_ID.',
                    ],
                    403,
                );
            }

            $request->attributes->set('auth', [
                'user_id' => $supabaseUserId,
                'internal_user_id' => $userContext['id'] ?? null,
                'tenant_id' => $tenantId,
                'role' => $userContext['role'] ?? ($decoded->role ?? null),
                'email' => $jwtEmail,
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
