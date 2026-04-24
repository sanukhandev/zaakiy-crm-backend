<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadActivityRepository
{
    public function create(string $tenantId, string $leadId, string $type, ?string $content, ?string $createdBy = null): object
    {
        $id = Str::uuid();
        $now = now();

        DB::table('lead_activities')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'lead_id' => $leadId,
            'type' => $type,
            'content' => $content,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById($tenantId, $id);
    }

    public function findById(string $tenantId, string $id): ?object
    {
        return DB::table('lead_activities')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();
    }

    public function findByLeadId(string $tenantId, string $leadId, int $limit = 50, int $offset = 0): array
    {
        return DB::table('lead_activities')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }

    public function countByLeadId(string $tenantId, string $leadId): int
    {
        return (int) DB::table('lead_activities')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->count();
    }
}
