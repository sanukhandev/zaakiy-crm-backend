<?php

namespace App\Services;

use App\DTOs\SendMessageDTO;
use App\Repositories\LeadRepository;
use App\Jobs\SendMessageJob;

class SendMessageService
{
    public function __construct(
        protected MessageService $messageService,
        protected LeadRepository $leadRepository,
        protected LeadActivityService $activityService,
        protected InboxService $inboxService,
    ) {}

    public function send(SendMessageDTO $dto): object
    {
        // Validate lead exists and belongs to tenant
        $lead = $this->leadRepository->findByIdForTenant($dto->leadId, $dto->tenantId);
        if (!$lead) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Lead not found');
        }

        $senderUserId = (string) ($dto->createdBy ?? 'system');
        $this->inboxService->assertCanSend($dto->tenantId, $dto->leadId, $senderUserId);

        // Prevent duplicate sends (idempotency)
        if ($dto->externalId) {
            $existing = $this->messageService->findByExternalId($dto->tenantId, $dto->externalId);
            if ($existing) {
                return $existing;
            }
        }

        $message = $this->messageService->createOutboundMessage(
            $dto->tenantId,
            $dto->leadId,
            $dto->channel,
            $dto->content,
            $dto->externalId,
            $dto->metadata,
            $dto->createdBy,
            'sent',
            $senderUserId,
        );

        // Log activity
        $this->activityService->logOutboundMessage(
            $dto->leadId,
            $dto->tenantId,
            $message->id,
            $dto->channel,
            $dto->content,
            $dto->createdBy,
        );

        // Queue actual send job
        dispatch(new SendMessageJob($message->id, $dto->tenantId));

        return $message;
    }
}
