<?php

namespace App\DTOs;

class WebhookLeadPayload
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly string $source,
        public readonly array $metadata,
    ) {}

    public static function fromArray(array $data, string $source): self
    {
        $name = isset($data['name']) ? trim((string) $data['name']) : null;
        $phone = isset($data['phone']) ? preg_replace('/\s+/', '', (string) $data['phone']) : null;
        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;

        return new self(
            $name !== '' ? $name : null,
            $phone !== '' ? $phone : null,
            $email !== '' ? $email : null,
            $source,
            $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'source' => $this->source,
            'metadata' => $this->metadata,
        ];
    }
}
