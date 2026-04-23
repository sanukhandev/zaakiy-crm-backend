<?php

namespace App\Services\Webhooks\Normalizers;

use App\DTOs\WebhookLeadPayload;
use InvalidArgumentException;

class WhatsAppNormalizer
{
    public function normalizeLead(array $payload): WebhookLeadPayload
    {
        $value = $payload['value']
            ?? $payload['entry'][0]['changes'][0]['value']
            ?? [];

        $phone = $payload['phone']
            ?? $payload['from']
            ?? $value['messages'][0]['from']
            ?? null;

        $name = $payload['name']
            ?? $payload['contact_name']
            ?? $value['contacts'][0]['profile']['name']
            ?? null;

        return WebhookLeadPayload::fromArray([
            'name' => $name,
            'phone' => $phone,
            'email' => $payload['email'] ?? null,
            'metadata' => $payload,
        ], 'whatsapp');
    }

    public function normalizeInboundMessage(array $payload): array
    {
        $value = $payload['value']
            ?? $payload['entry'][0]['changes'][0]['value']
            ?? [];

        $phone = $payload['phone']
            ?? $payload['from']
            ?? $value['messages'][0]['from']
            ?? null;

        $message = $payload['message']
            ?? $payload['text']
            ?? $value['messages'][0]['text']['body']
            ?? null;

        if (empty($phone) || empty($message)) {
            throw new InvalidArgumentException('Webhook message payload is invalid');
        }

        return [
            'phone' => preg_replace('/\s+/', '', (string) $phone),
            'message' => (string) $message,
            'direction' => 'inbound',
            'external_id' => (string) (
                $payload['external_id']
                ?? $payload['message_id']
                ?? $value['messages'][0]['id']
                ?? ''
            ) ?: null,
            'metadata' => $payload,
        ];
    }
}
