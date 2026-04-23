<?php

namespace App\Services;

use App\Repositories\TenantAutomationSettingsRepository;

class TenantAutomationSettingsService
{
    public function __construct(
        protected TenantAutomationSettingsRepository $repository,
    ) {}

    public function get(array $auth): array
    {
        $row = $this->repository->ensure($auth['tenant_id'], $auth['user_id'] ?? null);

        return $this->mapRow($row);
    }

    public function update(array $auth, array $payload): array
    {
        $row = $this->repository->update($auth['tenant_id'], $payload, $auth['user_id'] ?? null);

        return $this->mapRow($row);
    }

    public function resolve(string $tenantId): array
    {
        $row = $this->repository->ensure($tenantId);

        return $this->mapRow($row);
    }

    public function updateRoundRobinCursor(string $tenantId, ?string $userId): void
    {
        $this->repository->updateRoundRobinCursor($tenantId, $userId);
    }

    private function mapRow(?object $row): array
    {
        return [
            'auto_assignment_enabled' => (bool) ($row->auto_assignment_enabled ?? true),
            'assignment_strategy' => $row->assignment_strategy ?? 'least_load',
            'round_robin_last_user_id' => $row->round_robin_last_user_id ?? null,
            'auto_reply_enabled' => (bool) ($row->auto_reply_enabled ?? false),
            'auto_reply_template' => $row->auto_reply_template ?? 'Thanks for reaching out. Our team will get back to you shortly.',
            'follow_up_threshold_minutes' => (int) ($row->follow_up_threshold_minutes ?? 60),
            'updated_at' => $row->updated_at ?? null,
        ];
    }
}
