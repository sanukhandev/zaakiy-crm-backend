<?php

namespace App\DTOs;

class MessageStatusUpdateDTO
{
    public function __construct(
        public string $tenantId,
        public string $externalId,
        public string $status,
        public ?array $payload = null,
    ) {}

    public static function fromWebhook(array $payload): self
    {
        $status = strtolower((string) ($payload['status'] ?? throw new \InvalidArgumentException('status required')));

        if (!in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        return new self(
            tenantId: $payload['tenant_id'] ?? throw new \InvalidArgumentException('tenant_id required'),
            externalId: $payload['external_id'] ?? throw new \InvalidArgumentException('external_id required'),
            status: $status,
            payload: $payload,
        );
    }
}
