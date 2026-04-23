<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageRepository
{
    public function createInbound(array $payload): string
    {
        $id = (string) Str::uuid();

        DB::table('messages')->insert([
            'id' => $id,
            'tenant_id' => $payload['tenant_id'],
            'lead_id' => null,
            'channel' => 'whatsapp',
            'sender' => $payload['phone'],
            'content' => $payload['message'],
            'direction' => $payload['direction'] ?? 'inbound',
            'external_id' => $payload['external_id'] ?? null,
            'created_at' => now(),
        ]);

        return $id;
    }

    public function createOutbound(array $payload): string
    {
        $id = (string) Str::uuid();

        DB::table('messages')->insert([
            'id' => $id,
            'tenant_id' => $payload['tenant_id'],
            'lead_id' => $payload['lead_id'],
            'channel' => 'whatsapp',
            'sender' => $payload['sender'] ?? 'crm',
            'content' => $payload['message'],
            'direction' => 'outbound',
            'external_id' => $payload['external_id'] ?? null,
            'created_at' => now(),
        ]);

        return $id;
    }

    public function linkLead(string $messageId, string $tenantId, string $leadId): void
    {
        DB::table('messages')
            ->where('id', $messageId)
            ->where('tenant_id', $tenantId)
            ->update([
                'lead_id' => $leadId,
            ]);
    }

    public function getForLead(string $leadId, string $tenantId, int $perPage = 50): array
    {
        return DB::table('messages')
            ->where('lead_id', $leadId)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'asc')
            ->limit($perPage)
            ->get()
            ->all();
    }

    public function findByExternalId(string $tenantId, string $externalId): ?object
    {
        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('external_id', $externalId)
            ->first();
    }

    public function countForLeadByDirection(string $leadId, string $tenantId, string $direction): int
    {
        return (int) DB::table('messages')
            ->where('lead_id', $leadId)
            ->where('tenant_id', $tenantId)
            ->where('direction', $direction)
            ->count();
    }
}
