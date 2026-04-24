<?php

namespace App\Adapters;

use App\Services\TenantWhatsAppIntegrationService;
use Illuminate\Support\Facades\Http;

class WhatsAppAdapter
{
    public function __construct(protected TenantWhatsAppIntegrationService $integrationService) {}

    public function sendMessage(string $content, string $leadId, string $tenantId): array
    {
        $config = $this->integrationService->resolveProviderConfig($tenantId);

        if (empty($config['base_url']) || empty($config['phone_number_id']) || empty($config['access_token'])) {
            return ['success' => false, 'error' => 'WhatsApp provider configuration is incomplete'];
        }

        $url = rtrim((string) $config['base_url'], '/') . '/' . trim((string) ($config['api_version'] ?? 'v21.0'), '/') . '/' . $config['phone_number_id'] . '/messages';

        $response = Http::timeout(10)
            ->withToken((string) $config['access_token'])
            ->acceptJson()
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $leadId,
                'type' => 'text',
                'text' => ['body' => $content],
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => 'Provider rejected message',
                'status' => $response->status(),
                'body' => $response->json(),
            ];
        }

        return [
            'success' => true,
            'external_id' => data_get($response->json(), 'messages.0.id'),
            'payload' => $response->json(),
        ];
    }
}
