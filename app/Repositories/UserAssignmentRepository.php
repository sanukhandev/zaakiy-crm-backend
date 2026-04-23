<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserAssignmentRepository
{
    public function listSalesUsers(string $tenantId): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('role', 'sales')
            ->orderBy('created_at')
            ->get(['id', 'created_at'])
            ->all();
    }

    public function findLeastLoadedSalesUserId(string $tenantId): ?string
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        $query = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('role', 'sales');

        if (Schema::hasColumn('users', 'current_load')) {
            $query->orderBy('current_load');
        }

        $user = $query
            ->orderBy('created_at')
            ->first(['id']);

        return $user?->id;
    }

    public function incrementCurrentLoad(string $tenantId, string $userId, int $amount = 1): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'current_load') || $amount <= 0) {
            return;
        }

        DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->update([
                'current_load' => DB::raw('COALESCE(current_load, 0) + ' . (int) $amount),
            ]);
    }

    public function decrementCurrentLoad(string $tenantId, string $userId, int $amount = 1): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'current_load') || $amount <= 0) {
            return;
        }

        DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->update([
                'current_load' => DB::raw('GREATEST(COALESCE(current_load, 0) - ' . (int) $amount . ', 0)'),
            ]);
    }
}
