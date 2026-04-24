<?php

namespace App\DTOs;

class ClaimConversationDTO
{
    public function __construct(
        public string $tenantId,
        public string $leadId,
        public string $userId,
        public int $lockSeconds = 300,
    ) {}

    public static function fromAuth(string $leadId, array $auth, array $payload = []): self
    {
        return new self(
            tenantId: (string) $auth['tenant_id'],
            leadId: $leadId,
            userId: (string) ($auth['internal_user_id'] ?? $auth['user_id']),
            lockSeconds: max(60, min((int) ($payload['lock_seconds'] ?? 300), 3600)),
        );
    }
}
