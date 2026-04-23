<?php

namespace App\Services;

use App\Repositories\LeadRepository;
use App\Repositories\MessageRepository;
use Illuminate\Support\Facades\DB;

class WhatsAppService
{
    public function __construct(
        protected MessageRepository $messageRepository,
        protected LeadRepository $leadRepository,
        protected LeadService $leadService,
    ) {}

    public function ingestInbound(string $tenantId, array $payload): array
    {
        return DB::transaction(function () use ($tenantId, $payload) {
            $messageId = $this->messageRepository->createInbound([
                'tenant_id' => $tenantId,
                'phone' => $payload['phone'],
                'message' => $payload['message'],
                'direction' => $payload['direction'] ?? 'inbound',
                'external_id' => $payload['external_id'] ?? null,
            ]);

            $lead = $this->leadRepository->findByPhone($tenantId, $payload['phone']);

            if (!$lead) {
                $created = $this->leadService->createOrUpdateLeadFromWebhook($tenantId, [
                    'name' => $payload['phone'],
                    'phone' => $payload['phone'],
                    'email' => null,
                    'source' => 'whatsapp',
                    'metadata' => $payload['metadata'] ?? [],
                ]);

                $lead = $this->leadRepository->findByIdForTenant($created['id'], $tenantId);
            }

            $this->messageRepository->linkLead($messageId, $tenantId, $lead->id);

            $this->leadRepository->addActivity($lead->id, [
                'tenant_id' => $tenantId,
                'user_id' => null,
            ], [
                'type' => 'whatsapp',
                'content' => $payload['message'],
            ]);

            return [
                'message_id' => $messageId,
                'lead_id' => $lead->id,
            ];
        });
    }
}
