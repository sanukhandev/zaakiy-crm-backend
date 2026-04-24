<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
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
        return (int) DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->count();
    }

    public function findLastInboundByLeadId(string $tenantId, string $leadId): ?object
    {
        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->where('direction', 'inbound')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function countInboundByLeadId(string $tenantId, string $leadId): int
    {
        return (int) DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->where('direction', 'inbound')
            ->count();
    }

    public function updateStatus(string $tenantId, string $messageId, string $status): bool
    {
        return (bool) DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('id', $messageId)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
    }

    public function updateStatusByExternalId(string $tenantId, string $externalId, string $status): bool
    {
        return (bool) DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('external_id', $externalId)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
    }

    public function getInboxMessages(
        string $tenantId,
        ?string $assignedTo = null,
        bool $unreadOnly = false,
        bool $needsFollowUp = false,
        int $limit = 50,
        int $offset = 0
    ): array {
        $query = DB::table('messages')
            ->join('leads', 'messages.lead_id', '=', 'leads.id')
            ->where('messages.tenant_id', $tenantId)
            ->where('messages.direction', 'inbound');

        if ($assignedTo) {
            $query->where('leads.assigned_to', $assignedTo);
        }

        if ($unreadOnly) {
            $query->where('leads.unread_count', '>', 0);
        }

        if ($needsFollowUp) {
            $query->where('leads.needs_follow_up', true);
        }

        return $query
            ->select(
                'leads.id as lead_id',
                'leads.name as lead_name',
                'messages.content as last_message',
                'messages.created_at as last_message_at',
                'leads.unread_count',
                'leads.assigned_to',
                'messages.channel',
                'leads.needs_follow_up'
            )
            ->distinct('messages.lead_id')
            ->orderByDesc('messages.created_at')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }

    public function getConversationMessages(string $tenantId, string $leadId, int $limit = 100): array
    {
        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
