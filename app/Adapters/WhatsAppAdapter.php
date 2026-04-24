<?php

namespace App\Adapters;

use App\Services\WhatsAppService;

class WhatsAppAdapter
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
    ) {}

    public function sendMessage(string $content, string $leadId, string $tenantId): array
    {
        $response = $this->whatsAppService->sendOutbound([
            'tenant_id' => $tenantId,
            'user_id' => null,
        ], $leadId, $content);

        return [
            'success' => true,
            'external_id' => $response['external_id'] ?? null,
            'provider_response' => $response,
        ];
    }

    public function receiveWebhook(string $tenantId, array $normalizedPayload): array
    {
        return $this->whatsAppService->ingestInbound($tenantId, $normalizedPayload);
    }
}
