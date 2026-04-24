<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MessageRepository
{
    public function create(
        string $tenantId,
        string $leadId,
        string $channel,
        string $direction,
        string $content,
        ?string $externalId = null,
        ?string $createdBy = null,
    ): object {
        $id = Str::uuid();
        $now = now();

        DB::table('messages')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'lead_id' => $leadId,
            'channel' => $channel,
            'direction' => $direction,
            'content' => $content,
            'external_id' => $externalId,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById($tenantId, $id);
    }

    public function findById(string $tenantId, string $id): ?object
    {
        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();
    }

    public function findByExternalId(string $tenantId, string $externalId): ?object
    {
        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('external_id', $externalId)
            ->first();
    }

    public function findByLeadId(string $tenantId, string $leadId, int $limit = 50, int $offset = 0): array
    {
        // Return empty array if table doesn't exist
        if (!Schema::hasTable('messages')) {
            return [];
        }

        return DB::table('messages')
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
        if (!Schema::hasTable('messages')) {
            return 0;
        }

        return (int) DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->count();
    }

    public function findLastInboundByLeadId(string $tenantId, string $leadId): ?object
    {
        if (!Schema::hasTable('messages')) {
            return null;
        }

        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->where('direction', 'inbound')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function countInboundByLeadId(string $tenantId, string $leadId): int
    {
        if (!Schema::hasTable('messages')) {
            return 0;
        }

        return (int) DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->where('direction', 'inbound')
            ->count();
    }
}
