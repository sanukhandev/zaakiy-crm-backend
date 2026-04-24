<?php

namespace App\DTOs;

class MessageStatusUpdateDTO
{
    public function __construct(
        public string $tenantId,
        public string $externalId,
        public string $status,
    ) {}

    public static function fromWebhook(array $payload): self
    {
        return new self(
            tenantId: $payload['tenant_id'] ?? throw new \InvalidArgumentException('tenant_id required'),
            externalId: $payload['external_id'] ?? throw new \InvalidArgumentException('external_id required'),
            status: $payload['status'] ?? throw new \InvalidArgumentException('status required'),
        );
    }
}
