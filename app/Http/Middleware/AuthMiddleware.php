<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;

class AuthMiddleware
{
    private static ?bool $usersTableExists = null;
    private static ?bool $usersHasSupabaseUserIdColumn = null;

    private function usersTableExists(): bool
    {
        if (self::$usersTableExists === null) {
            self::$usersTableExists = Schema::hasTable('users');
        }

        return self::$usersTableExists;
    }

    private function usersHasSupabaseUserIdColumn(): bool
    {
        if (self::$usersHasSupabaseUserIdColumn === null) {
            self::$usersHasSupabaseUserIdColumn = Schema::hasColumn(
                'users',
                'supabase_user_id',
            );
        }

        return self::$usersHasSupabaseUserIdColumn;
    }

    private function jwtAlg(string $token): ?string
    {
        $parts = explode('.', $token);

        if (count($parts) < 2) {
            return null;
        }

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);

        return is_array($header) ? ($header['alg'] ?? null) : null;
    }

    private function decodeToken(string $token): object
    {
        $jwtSecret = config('services.supabase.jwt_secret');
        $alg = $this->jwtAlg($token);

        if (!empty($jwtSecret) && $alg === 'HS256') {
            return JWT::decode($token, new Key($jwtSecret, 'HS256'));
        }

        $jwks = Cache::remember('supabase_jwks', 21600, function () {
            $http = Http::timeout(5)->withHeaders([
                'apikey' => config('services.supabase.api_key'),
                'Accept' => 'application/json',
            ]);

            if (!app()->environment('production')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->get(
                config('services.supabase.url') . '/auth/v1/.well-known/jwks.json',
            );

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch JWKS: ' . $response->body());
            }

            $json = $response->json();

            if (!isset($json['keys'])) {
                throw new \Exception('Invalid JWKS response: ' . json_encode($json));
            }

            return $json;
        });

        $keys = JWK::parseKeySet($jwks);

        return JWT::decode($token, $keys);
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                Log::warning('Authentication failed: bearer token missing', [
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'No token',
                        'errors' => [
                            'auth' => ['Bearer token is required'],
                        ],
                    ],
                    401,
                );
            }

            $decoded = $this->decodeToken($token);

            $supabaseUserId = $decoded->sub ?? null;
            $jwtEmail = $decoded->email ?? null;

            if (!$supabaseUserId) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Invalid token',
                        'errors' => [
                            'auth' => ['JWT subject is missing'],
                        ],
                    ],
                    401,
                );
            }

            $userContext = Cache::remember(
                'auth_user_ctx_' . $supabaseUserId,
                300,
                function () use ($supabaseUserId, $jwtEmail) {
                    try {
                        if (!$this->usersTableExists()) {
                            return null;
                        }

                        $user = null;

                        if ($this->usersHasSupabaseUserIdColumn()) {
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
                        'success' => false,
                        'message' =>
                            'Tenant context could not be resolved. Provide tenant_id claim, X-Tenant-Id header, or SUPABASE_DEFAULT_TENANT_ID.',
                        'errors' => [
                            'tenant_id' => [
                                'Tenant context could not be resolved',
                            ],
                        ],
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
            Log::warning('Authentication failed', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Unauthorized',
                    'errors' => [
                        'auth' => [$e->getMessage()],
                    ],
                ],
                401,
            );
        }
    }
}
