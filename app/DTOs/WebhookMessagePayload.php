<?php

namespace App\DTOs;

use App\Support\PhoneNumber;

class WebhookMessagePayload
{
    public function __construct(
        public readonly string $channel,
        public readonly string $direction,
        public readonly string $content,
        public readonly ?string $sender,
        public readonly ?string $externalId,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly array $metadata,
    ) {}

    public static function fromArray(array $data, string $channel, string $direction = 'inbound'): self
    {
        return new self(
            channel: $channel,
            direction: $direction,
            content: trim((string) ($data['content'] ?? $data['message'] ?? '')),
            sender: isset($data['sender']) ? trim((string) $data['sender']) : null,
            externalId: isset($data['external_id']) && trim((string) $data['external_id']) !== ''
                ? trim((string) $data['external_id'])
                : null,
            phone: PhoneNumber::normalize($data['phone'] ?? null),
            email: isset($data['email']) ? strtolower(trim((string) $data['email'])) : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'direction' => $this->direction,
            'content' => $this->content,
            'sender' => $this->sender,
            'external_id' => $this->externalId,
            'phone' => $this->phone,
            'email' => $this->email,
            'metadata' => $this->metadata,
        ];
    }
}