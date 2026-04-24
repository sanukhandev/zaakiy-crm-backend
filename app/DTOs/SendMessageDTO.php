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
        public ?array $metadata = null,
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
            metadata: self::extractMetadata($data),
        );
    }

    private static function extractMetadata(array $data): ?array
    {
        $metadata = [];

        if (!empty($data['template_id'])) {
            $metadata['template_id'] = (string) $data['template_id'];
        }

        if (!empty($data['quick_reply'])) {
            $metadata['quick_reply'] = (string) $data['quick_reply'];
        }

        if (isset($data['attachments']) && is_array($data['attachments'])) {
            $metadata['attachments'] = $data['attachments'];
        }

        return $metadata === [] ? null : $metadata;
    }
}
