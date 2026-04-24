<?php

namespace App\Services;

use App\DTOs\WebhookLeadPayload;
use App\DTOs\WebhookMessagePayload;
use App\Repositories\LeadRepository;
use InvalidArgumentException;

class LeadLinkingService
{
    public function __construct(
        protected LeadRepository $leadRepository,
        protected LeadService $leadService,
        protected MessageService $messageService,
    ) {}

    public function resolveLead(
        string $tenantId,
        WebhookLeadPayload $leadPayload,
        ?WebhookMessagePayload $messagePayload = null,
        bool $createIfMissing = true,
    ): array {
        if ($messagePayload?->externalId) {
            $existingMessage = $this->messageService->findByExternalId($tenantId, $messagePayload->externalId);

            if ($existingMessage) {
                $lead = $this->leadRepository->findByIdForTenant((string) $existingMessage->lead_id, $tenantId);

                if ($lead) {
                    return ['lead' => $lead, 'action' => 'matched_external_message'];
                }
            }
        }

        $lead = $this->leadRepository->findByPhoneOrEmailAndTenant(
            $tenantId,
            $messagePayload?->phone ?? $leadPayload->phone,
            $messagePayload?->email ?? $leadPayload->email,
        );

        if ($lead) {
            return ['lead' => $lead, 'action' => 'matched_duplicate'];
        }

        if (!$createIfMissing) {
            return ['lead' => null, 'action' => 'not_found'];
        }

        if (!$leadPayload->phone && !$leadPayload->email) {
            throw new InvalidArgumentException('Lead linking requires at least phone or email');
        }

        $result = $this->leadService->createOrUpdateLeadFromWebhook($tenantId, $leadPayload->toArray());
        $lead = $this->leadRepository->findByIdForTenant((string) $result['id'], $tenantId);

        return ['lead' => $lead, 'action' => $result['action'] ?? 'created'];
    }
}