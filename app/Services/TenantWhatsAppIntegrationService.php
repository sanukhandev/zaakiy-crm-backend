<?php

namespace App\Services;

use App\Repositories\TenantWhatsAppIntegrationRepository;

class TenantWhatsAppIntegrationService
{
    public function __construct(
        protected TenantWhatsAppIntegrationRepository $repository,
    ) {}

    public function getTenantConfig(array $auth): array
    {
        $row = $this->repository->findByTenantId($auth['tenant_id']);

        return [
            'is_configured' => (bool) ($row && $row->phone_number_id && $row->access_token_encrypted),
            'is_active' => (bool) ($row->is_active ?? false),
            'business_account_id' => $row->business_account_id ?? null,
            'phone_number_id' => $row->phone_number_id ?? null,
            'sender_label' => $row->sender_label ?? config('services.whatsapp.sender_label', 'CRM'),
            'base_url' => $row->base_url ?? config('services.whatsapp.base_url'),
            'api_version' => $row->api_version ?? config('services.whatsapp.api_version', 'v21.0'),
            'has_access_token' => !empty($row->access_token_encrypted),
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    public function updateTenantConfig(array $auth, array $payload): array
    {
        $existing = $this->repository->findByTenantId($auth['tenant_id']);

        $this->repository->upsert($auth['tenant_id'], [
            'business_account_id' => $payload['business_account_id'] ?? null,
            'phone_number_id' => $payload['phone_number_id'] ?? null,
            'access_token' => $payload['access_token'] ?? null,
            'sender_label' => $payload['sender_label'] ?? config('services.whatsapp.sender_label', 'CRM'),
            'base_url' => $payload['base_url'] ?? config('services.whatsapp.base_url'),
            'api_version' => $payload['api_version'] ?? config('services.whatsapp.api_version', 'v21.0'),
            'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true,
            'created_by' => $existing->created_by ?? $auth['user_id'],
            'updated_by' => $auth['user_id'],
            'created_at' => $existing->created_at ?? now(),
        ], $existing->access_token_encrypted ?? null);

        return $this->getTenantConfig($auth);
    }

    public function resolveProviderConfig(string $tenantId): array
    {
        $row = $this->repository->findByTenantId($tenantId);
        $tenantToken = $this->repository->decryptAccessToken($row);

        return [
            'base_url' => $row->base_url ?? config('services.whatsapp.base_url'),
            'api_version' => $row->api_version ?? config('services.whatsapp.api_version', 'v21.0'),
            'phone_number_id' => $row->phone_number_id ?? config('services.whatsapp.phone_number_id'),
            'access_token' => $tenantToken ?? config('services.whatsapp.access_token'),
            'sender_label' => $row->sender_label ?? config('services.whatsapp.sender_label', 'CRM'),
            'is_active' => (bool) ($row->is_active ?? true),
        ];
    }
}
