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

    public function linkLead(string $messageId, string $tenantId, string $leadId): void
    {
        DB::table('messages')
            ->where('id', $messageId)
            ->where('tenant_id', $tenantId)
            ->update([
                'lead_id' => $leadId,
            ]);
    }
}
