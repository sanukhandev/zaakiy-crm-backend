<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TenantWhatsAppIntegrationRepository
{
    public function findByTenantId(string $tenantId): ?object
    {
        return DB::table('tenant_whatsapp_integrations')
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function upsert(string $tenantId, array $payload, ?string $existingEncryptedToken = null): void
    {
        $now = now();
        $accessToken = $payload['access_token'] ?? null;
        $encryptedToken = $existingEncryptedToken;

        if (is_string($accessToken) && trim($accessToken) !== '') {
            $encryptedToken = Crypt::encryptString(trim($accessToken));
        }

        DB::table('tenant_whatsapp_integrations')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'business_account_id' => $payload['business_account_id'] ?? null,
                'phone_number_id' => $payload['phone_number_id'] ?? null,
                'access_token_encrypted' => $encryptedToken,
                'sender_label' => $payload['sender_label'] ?? null,
                'base_url' => $payload['base_url'] ?? null,
                'api_version' => $payload['api_version'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'created_by' => $payload['created_by'] ?? null,
                'updated_by' => $payload['updated_by'] ?? null,
                'updated_at' => $now,
                'created_at' => $payload['created_at'] ?? $now,
            ],
        );
    }

    public function decryptAccessToken(?object $row): ?string
    {
        if (!$row || empty($row->access_token_encrypted)) {
            return null;
        }

        return Crypt::decryptString($row->access_token_encrypted);
    }
}
