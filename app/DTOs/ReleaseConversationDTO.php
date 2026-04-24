<?php

namespace App\DTOs;

class ReleaseConversationDTO
{
    public function __construct(
        public string $tenantId,
        public string $leadId,
        public string $userId,
        public string $role = '',
    ) {}

    public static function fromAuth(string $leadId, array $auth): self
    {
        return new self(
            tenantId: (string) $auth['tenant_id'],
            leadId: $leadId,
            userId: (string) ($auth['internal_user_id'] ?? $auth['user_id']),
            role: strtolower((string) ($auth['role'] ?? '')),
        );
    }
}
