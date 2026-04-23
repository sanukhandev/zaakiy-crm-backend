<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TenantAutomationSettingsRepository
{
    public function findByTenantId(string $tenantId): ?object
    {
        return DB::table('tenant_automation_settings')
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function ensure(string $tenantId, ?string $userId = null): object
    {
        $existing = $this->findByTenantId($tenantId);
        if ($existing) {
            return $existing;
        }

        $now = now();

        DB::table('tenant_automation_settings')->insert([
            'tenant_id' => $tenantId,
            'auto_assignment_enabled' => true,
            'assignment_strategy' => 'least_load',
            'round_robin_last_user_id' => null,
            'auto_reply_enabled' => false,
            'auto_reply_template' => 'Thanks for reaching out. Our team will get back to you shortly.',
            'follow_up_threshold_minutes' => 60,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByTenantId($tenantId);
    }

    public function update(string $tenantId, array $payload, ?string $userId = null): object
    {
        $this->ensure($tenantId, $userId);

        DB::table('tenant_automation_settings')
            ->where('tenant_id', $tenantId)
            ->update([
                'auto_assignment_enabled' => (bool) ($payload['auto_assignment_enabled'] ?? true),
                'assignment_strategy' => $payload['assignment_strategy'] ?? 'least_load',
                'auto_reply_enabled' => (bool) ($payload['auto_reply_enabled'] ?? false),
                'auto_reply_template' => $payload['auto_reply_template'] ?? null,
                'follow_up_threshold_minutes' => (int) ($payload['follow_up_threshold_minutes'] ?? 60),
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

        return $this->findByTenantId($tenantId);
    }

    public function updateRoundRobinCursor(string $tenantId, ?string $userId): void
    {
        $this->ensure($tenantId, $userId);

        DB::table('tenant_automation_settings')
            ->where('tenant_id', $tenantId)
            ->update([
                'round_robin_last_user_id' => $userId,
                'updated_at' => now(),
            ]);
    }
}
