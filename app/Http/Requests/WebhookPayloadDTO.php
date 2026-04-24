<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookPayloadDTO
{
    public function __construct(
        public string $name,
        public string $phone,
        public ?string $email,
        public string $source,
        public string $message,
        public string $external_id,
        public string $channel,
        public ?int $timestamp = null,
    ) {}

    public static function fromWhatsApp(array $data): self
    {
        $contact = $data['contacts'][0] ?? [];
        $message = $data['messages'][0] ?? [];

        return new self(
            name: $contact['profile']['name'] ?? 'Unknown',
            phone: $contact['wa_id'] ?? '',
            email: null,
            source: 'whatsapp',
            message: $message['text']['body'] ?? '',
            external_id: $message['id'] ?? '',
            channel: 'whatsapp',
            timestamp: $message['timestamp'] ?? null,
        );
    }

    public static function fromMeta(array $data): self
    {
        $sender = $data['sender'] ?? [];
        $message = $data['message'] ?? [];

        return new self(
            name: $sender['name'] ?? 'Unknown',
            phone: $sender['phone'] ?? '',
            email: $sender['email'] ?? null,
            source: 'meta',
            message: $message['text'] ?? '',
            external_id: $message['id'] ?? '',
            channel: 'meta',
            timestamp: $message['timestamp'] ?? null,
        );
    }
}
