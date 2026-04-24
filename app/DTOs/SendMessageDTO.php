<?php

namespace App\DTOs;

class SendMessageDTO
{
    public function __construct(
        public string $tenantId,
        public string $leadId,
        public string $channel,
        public string $content,
        public ?string $createdBy = null,
        public ?string $externalId = null,
    ) {}

    public static function fromRequest(array $data, string $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            leadId: $data['lead_id'] ?? throw new \InvalidArgumentException('lead_id required'),
            channel: $data['channel'] ?? 'whatsapp',
            content: $data['content'] ?? throw new \InvalidArgumentException('content required'),
            createdBy: $data['created_by'] ?? null,
            externalId: $data['external_id'] ?? null,
        );
    }
}
