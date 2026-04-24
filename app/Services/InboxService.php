<?php

namespace App\Services;

use App\DTOs\ClaimConversationDTO;
use App\DTOs\ReleaseConversationDTO;
use App\Repositories\LeadRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InboxService
{
    public function __construct(
        protected LeadRepository $leadRepository,
        protected MessageService $messageService,
    ) {}

    public function claimConversation(ClaimConversationDTO $dto): object
    {
        $lead = $this->leadRepository->claimConversation($dto->tenantId, $dto->leadId, $dto->userId, $dto->lockSeconds);

        if (!$lead) {
            throw new ModelNotFoundException('Lead not found');
        }

        $isOwnedByOther = !empty($lead->conversation_owner_id)
            && (string) $lead->conversation_owner_id !== $dto->userId
            && !empty($lead->conversation_lock_expires_at)
            && now()->lessThan($lead->conversation_lock_expires_at);

        if ($isOwnedByOther) {
            throw new \RuntimeException('Conversation currently locked by another user');
        }

        return $lead;
    }

    public function releaseConversation(ReleaseConversationDTO $dto): void
    {
        $lead = $this->leadRepository->findById($dto->tenantId, $dto->leadId);

        if (!$lead) {
            throw new ModelNotFoundException('Lead not found');
        }

        $isOwner = (string) ($lead->conversation_owner_id ?? '') === $dto->userId;
        $isPrivileged = in_array($dto->role, ['admin', 'owner'], true);

        if (!$isOwner && !$isPrivileged) {
            throw new \RuntimeException('Not allowed to release this conversation');
        }

        $this->leadRepository->releaseConversation($dto->tenantId, $dto->leadId);
    }

    public function markConversationAsRead(string $tenantId, string $leadId): void
    {
        $lead = $this->leadRepository->findById($tenantId, $leadId);
        if (!$lead) {
            throw new ModelNotFoundException('Lead not found');
        }

        $this->messageService->markLeadMessagesAsRead($tenantId, $leadId);
    }

    public function assertCanSend(string $tenantId, string $leadId, string $userId): void
    {
        if (!$this->leadRepository->canUserSendInConversation($tenantId, $leadId, $userId)) {
            throw new \RuntimeException('Conversation is currently locked by another user');
        }
    }
}
