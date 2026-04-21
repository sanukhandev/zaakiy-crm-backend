<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token missing'
                ], 401);
            }

            $decoded = $this->decodeSupabaseToken($token);

            // Extract user ID from token
            $userId = $decoded->sub ?? null;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token (no user)'
                ], 401);
            }

            // Attach context from JWT claims
            $request->attributes->set('auth', [
                'user_id' => $userId,
                'email' => $decoded->email ?? null,
                'role' => $decoded->role ?? 'authenticated',
                'tenant_id' => $decoded->tenant_id ?? null,
                'jwt' => $decoded,
            ]);

            return $next($request);

        } catch (Throwable $e) {
            $payload = [
                'success' => false,
                'message' => 'Unauthorized',
            ];

            if (app()->environment('local')) {
                $payload['error'] = $e->getMessage();
            }

            return response()->json($payload, 401);
        }
    }

    private function decodeSupabaseToken(string $token): object
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \RuntimeException('Malformed token');
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $alg = $header['alg'] ?? null;

        if (! is_string($alg) || $alg === '') {
            throw new \RuntimeException('Token algorithm missing');
        }

        if ($alg === 'HS256') {
            $secret = config('services.supabase.jwt_secret');

            if (! is_string($secret) || trim($secret) === '') {
                throw new \RuntimeException('SUPABASE_JWT_SECRET is not configured');
            }

            return JWT::decode($token, new Key($secret, 'HS256'));
        }

        if (in_array($alg, ['RS256', 'ES256', 'EdDSA'], true)) {
            $jwks = Cache::remember('supabase_jwks', 600, function () {
                $url = rtrim((string) config('services.supabase.url'), '/').'/auth/v1/.well-known/jwks.json';

                $http = Http::timeout(8);
                if (! app()->environment('production')) {
                    $http = $http->withoutVerifying();
                }

                $response = $http->get($url);

                if (! $response->ok()) {
                    throw new \RuntimeException('Unable to fetch Supabase JWKS');
                }

                return $response->json();
            });

            return JWT::decode($token, JWK::parseKeySet($jwks));
        }

        throw new \RuntimeException('Unsupported JWT algorithm: '.$alg);
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}