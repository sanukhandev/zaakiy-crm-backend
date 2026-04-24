<?php

namespace App\Services;

use App\DTOs\SendMessageDTO;
use App\Repositories\MessageRepository;
use App\Repositories\LeadRepository;
use App\Jobs\SendMessageJob;
use Illuminate\Support\Facades\DB;

class SendMessageService
{
    public function __construct(
        protected MessageRepository $messageRepository,
        protected LeadRepository $leadRepository,
        protected LeadActivityService $activityService,
    ) {}

    public function send(SendMessageDTO $dto): object
    {
        // Validate lead exists and belongs to tenant
        $lead = $this->leadRepository->findByIdForTenant($dto->leadId, $dto->tenantId);
        if (!$lead) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Lead not found');
        }

        // Prevent duplicate sends (idempotency)
        if ($dto->externalId) {
            $existing = $this->messageRepository->findByExternalId($dto->tenantId, $dto->externalId);
            if ($existing) {
                return $existing;
            }
        }

        // Create outbound message record
        $message = $this->messageRepository->create(
            $dto->tenantId,
            $dto->leadId,
            $dto->channel,
            'outbound',
            $dto->content,
            $dto->externalId,
            $dto->createdBy,
        );

        // Update lead conversation metadata
        $this->leadRepository->updateConversationMetadata(
            $dto->tenantId,
            $dto->leadId,
            'outbound'
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
