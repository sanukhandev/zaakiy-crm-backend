<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookKeyController extends Controller
{
    use ApiResponse;

    private function resolveAuth(Request $request): ?array
    {
        $auth = $request->attributes->get('auth');

        if (!is_array($auth) || empty($auth['tenant_id']) || empty($auth['user_id'])) {
            return null;
        }

        return $auth;
    }

    public function showWhatsAppKey(Request $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $row = DB::table('tenant_webhook_keys')
            ->where('tenant_id', $auth['tenant_id'])
            ->where('provider', 'whatsapp')
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();

        return $this->success([
            'has_key' => (bool) $row,
            'key_prefix' => $row->key_prefix ?? null,
            'created_at' => $row->created_at ?? null,
            'last_used_at' => $row->last_used_at ?? null,
            'webhook_url' => rtrim((string) config('app.url'), '/') . '/api/v1/webhooks/whatsapp',
        ], 'Webhook key status fetched');
    }

    public function regenerateWhatsAppKey(Request $request): JsonResponse
    {
        $auth = $this->resolveAuth($request);
        if (!$auth) {
            return $this->failure('Unauthorized', null, [], 401);
        }

        $plainKey = 'whk_' . Str::random(40);
        $keyHash = hash('sha256', $plainKey);
        $now = now();

        DB::transaction(function () use ($auth, $keyHash, $plainKey, $now) {
            DB::table('tenant_webhook_keys')
                ->where('tenant_id', $auth['tenant_id'])
                ->where('provider', 'whatsapp')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('tenant_webhook_keys')->insert([
                'tenant_id' => $auth['tenant_id'],
                'provider' => 'whatsapp',
                'key_hash' => $keyHash,
                'key_prefix' => substr($plainKey, 0, 12),
                'created_by' => $auth['user_id'],
                'last_used_at' => null,
                'revoked_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return $this->success([
            'api_key' => $plainKey,
            'key_prefix' => substr($plainKey, 0, 12),
            'webhook_url' => rtrim((string) config('app.url'), '/') . '/api/v1/webhooks/whatsapp',
            'generated_at' => $now->toIso8601String(),
        ], 'Webhook key regenerated successfully', [], 201);
    }
}
