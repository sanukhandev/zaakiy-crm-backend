<?php

namespace App\Services\Webhooks\Normalizers;

use App\DTOs\WebhookLeadPayload;

class MetaNormalizer
{
    public function normalize(array $payload): WebhookLeadPayload
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];

        $contactName = $value['contacts'][0]['profile']['name'] ?? null;
        $messagePhone = $value['messages'][0]['from'] ?? null;

        return WebhookLeadPayload::fromArray([
            'name' => $payload['name'] ?? $payload['lead']['name'] ?? $contactName,
            'phone' => $payload['phone'] ?? $payload['lead']['phone'] ?? $messagePhone,
            'email' => $payload['email'] ?? $payload['lead']['email'] ?? null,
            'metadata' => $payload,
        ], 'meta');
    }
}
