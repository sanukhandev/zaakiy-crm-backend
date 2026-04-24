<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MessageRepository
{
    public function createInboundMessage(
        string $tenantId,
        string $leadId,
        string $channel,
        string $content,
        ?string $externalId = null,
        ?array $metadata = null,
        ?string $createdBy = null,
        ?string $sender = null,
    ): object {
        return $this->createMessage(
            tenantId: $tenantId,
            leadId: $leadId,
            channel: $channel,
            direction: 'inbound',
            content: $content,
            externalId: $externalId,
            metadata: $metadata,
            createdBy: $createdBy,
            sender: $sender,
            defaultStatus: 'delivered'
        );
    }

    public function createOutboundMessage(
        string $tenantId,
        string $leadId,
        string $channel,
        string $content,
        ?string $externalId = null,
        ?array $metadata = null,
        ?string $createdBy = null,
        string $status = 'sent',
        ?string $sender = null,
    ): object {
        return $this->createMessage(
            tenantId: $tenantId,
            leadId: $leadId,
            channel: $channel,
            direction: 'outbound',
            content: $content,
            externalId: $externalId,
            metadata: $metadata,
            createdBy: $createdBy,
            sender: $sender,
            defaultStatus: $status
        );
    }

    private function createMessage(
        string $tenantId,
        string $leadId,
        string $channel,
        string $direction,
        string $content,
        ?string $externalId,
        ?array $metadata,
        ?string $createdBy,
        ?string $sender,
        string $defaultStatus,
    ): object {
        if ($externalId) {
            $existing = $this->findByExternalId($tenantId, $externalId);
            if ($existing) {
                return $existing;
            }
        }

        $id = Str::uuid();
        $now = now();

        $insert = [
            'id' => $id,
            'tenant_id' => $tenantId,
            'lead_id' => $leadId,
            'channel' => $channel,
            'direction' => $direction,
            'content' => $content,
            'external_id' => $externalId,
            'status' => $defaultStatus,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('messages', 'sender')) {
            $insert['sender'] = $sender;
        }

        DB::table('messages')->insert($insert);

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
            ->orderBy('created_at', 'asc')
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

    public function updateMessageStatus(string $tenantId, string $messageId, string $status, ?array $payload = null): bool
    {
        $message = $this->findById($tenantId, $messageId);
        if (!$message) {
            return false;
        }

        if (!$this->isAllowedStatusTransition((string) ($message->status ?? 'sent'), $status)) {
            return false;
        }

        $updated = (bool) DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('id', $messageId)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);

        if ($updated) {
            DB::table('message_status_events')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'message_id' => $messageId,
                'external_id' => $message->external_id,
                'status' => $status,
                'payload' => $payload ? json_encode($payload) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $updated;
    }

    public function updateStatusByExternalId(string $tenantId, string $externalId, string $status, ?array $payload = null): bool
    {
        $message = $this->findByExternalId($tenantId, $externalId);
        if (!$message) {
            return false;
        }

        return $this->updateMessageStatus($tenantId, (string) $message->id, $status, $payload);
    }

    public function getInboxMessagesPaginated(
        string $tenantId,
        ?string $assignedTo = null,
        bool $unreadOnly = false,
        bool $needsFollowUp = false,
        bool $ownedByMe = false,
        ?string $ownerId = null,
        int $perPage = 20,
        int $page = 1,
    ): LengthAwarePaginator {
        $lastMessageSubquery = DB::table('messages as m1')
            ->select('m1.lead_id', 'm1.content', 'm1.channel', 'm1.created_at')
            ->where('m1.tenant_id', $tenantId)
            ->whereRaw('m1.created_at = (select max(m2.created_at) from messages m2 where m2.tenant_id = m1.tenant_id and m2.lead_id = m1.lead_id)');

        $query = DB::table('leads')
            ->leftJoinSub($lastMessageSubquery, 'lm', function ($join) {
                $join->on('lm.lead_id', '=', 'leads.id');
            })
            ->where('leads.tenant_id', $tenantId)
            ->whereNull('leads.deleted_at');

        if ($assignedTo) {
            $query->where('leads.assigned_to', $assignedTo);
        }

        if ($unreadOnly) {
            $query->where('leads.unread_count', '>', 0);
        }

        if ($needsFollowUp) {
            $query->where('leads.needs_follow_up', true);
        }

        if ($ownedByMe && $ownerId) {
            $query->where('leads.conversation_owner_id', $ownerId)
                ->where(function ($q) {
                    $q->whereNull('leads.conversation_lock_expires_at')
                        ->orWhere('leads.conversation_lock_expires_at', '>', now());
                });
        }

        return $query
            ->select(
                'leads.id as lead_id',
                'leads.name as lead_name',
                'leads.phone',
                'lm.channel',
                'lm.content as last_message_preview',
                'leads.last_message_at',
                'leads.last_message_direction',
                'leads.unread_count',
                'leads.assigned_to',
                'leads.conversation_owner_id',
                'leads.conversation_lock_expires_at',
                'leads.needs_follow_up as pending_follow_up'
            )
            ->orderByDesc('leads.last_message_at')
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));
    }

    public function getConversationMessagesPaginated(string $tenantId, string $leadId, int $perPage = 50, int $page = 1): LengthAwarePaginator
    {
        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->orderBy('created_at', 'desc')
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));
    }

    public function markLeadMessagesAsRead(string $tenantId, string $leadId): int
    {
        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->where('direction', 'inbound')
            ->whereIn('status', ['sent', 'delivered'])
            ->update([
                'status' => 'read',
                'updated_at' => now(),
            ]);
    }

    public function appendMetadata(string $tenantId, string $messageId, array $metadata): bool
    {
        $message = $this->findById($tenantId, $messageId);
        if (!$message) {
            return false;
        }

        $existing = [];
        if (!empty($message->metadata) && is_string($message->metadata)) {
            $decoded = json_decode($message->metadata, true);
            $existing = is_array($decoded) ? $decoded : [];
        }

        return (bool) DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('id', $messageId)
            ->update([
                'metadata' => json_encode(array_merge($existing, $metadata)),
                'updated_at' => now(),
            ]);
    }

    private function isAllowedStatusTransition(string $current, string $next): bool
    {
        if ($current === $next) {
            return true;
        }

        $allowed = [
            'sending' => ['sent', 'failed'],
            'sent' => ['delivered', 'read', 'failed'],
            'delivered' => ['read'],
            'read' => [],
            'failed' => [],
        ];

        return in_array($next, $allowed[$current] ?? [], true);
    }
}
