<?php

namespace App\Services\Webhooks\Normalizers;

use App\DTOs\WebhookLeadPayload;
use App\DTOs\WebhookMessagePayload;
use App\Support\PhoneNumber;

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

    public function normalizeInboundMessage(array $payload): ?WebhookMessagePayload
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $content = $payload['message']
            ?? $payload['text']
            ?? $value['messages'][0]['text']['body']
            ?? null;

        if (!$content) {
            return null;
        }

        return WebhookMessagePayload::fromArray([
            'message' => $content,
            'external_id' => $payload['external_id']
                ?? $payload['message_id']
                ?? $value['messages'][0]['id']
                ?? null,
            'sender' => $payload['sender']
                ?? $value['contacts'][0]['profile']['name']
                ?? 'Meta lead',
            'phone' => PhoneNumber::normalize(
                $payload['phone']
                ?? $payload['lead']['phone']
                ?? $value['messages'][0]['from']
                ?? null,
            ),
            'email' => $payload['email'] ?? $payload['lead']['email'] ?? null,
            'metadata' => $payload,
        ], 'meta');
    }
}
