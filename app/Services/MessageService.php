<?php

namespace App\Services;

use App\Repositories\MessageRepository;
use App\Repositories\LeadRepository;

class MessageService
{
    public function __construct(
        protected MessageRepository $repository,
        protected LeadRepository $leadRepository,
    ) {}

    public function createInboundMessage(
        string $tenantId,
        string $leadId,
        string $channel,
        string $content,
        ?string $externalId = null,
        ?array $metadata = null,
    ): object {
        $message = $this->repository->createInboundMessage($tenantId, $leadId, $channel, $content, $externalId, $metadata);

        $this->leadRepository->incrementUnreadCount($tenantId, $leadId);
        $this->leadRepository->updateLeadConversationMetadata($tenantId, $leadId, 'inbound');

        return $message;
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
    ): object {
        $message = $this->repository->createOutboundMessage(
            $tenantId,
            $leadId,
            $channel,
            $content,
            $externalId,
            $metadata,
            $createdBy,
            $status,
        );

        $this->leadRepository->updateLeadConversationMetadata($tenantId, $leadId, 'outbound');

        return $message;
    }

    public function getLeadMessages(string $tenantId, string $leadId, int $perPage = 50, int $page = 1): array
    {
        return $this->repository->getConversationMessagesPaginated($tenantId, $leadId, $perPage, $page)->toArray();
    }

    public function countLeadMessages(string $tenantId, string $leadId): int
    {
        return $this->repository->countByLeadId($tenantId, $leadId);
    }

    public function findByExternalId(string $tenantId, string $externalId): ?object
    {
        return $this->repository->findByExternalId($tenantId, $externalId);
    }

    public function countInboundMessages(string $tenantId, string $leadId): int
    {
        return $this->repository->countInboundByLeadId($tenantId, $leadId);
    }

    public function getLastInboundMessage(string $tenantId, string $leadId): ?object
    {
        return $this->repository->findLastInboundByLeadId($tenantId, $leadId);
    }

    public function updateMessageStatus(string $tenantId, string $messageId, string $status): bool
    {
        return $this->repository->updateMessageStatus($tenantId, $messageId, $status);
    }

    public function updateMessageStatusByExternalId(string $tenantId, string $externalId, string $status, ?array $payload = null): bool
    {
        return $this->repository->updateStatusByExternalId($tenantId, $externalId, $status, $payload);
    }

    public function getInbox(
        string $tenantId,
        ?string $assignedTo = null,
        bool $unreadOnly = false,
        bool $needsFollowUp = false,
        bool $ownedByMe = false,
        ?string $ownerId = null,
        int $perPage = 20,
        int $page = 1,
    ): array {
        return $this->repository
            ->getInboxMessagesPaginated($tenantId, $assignedTo, $unreadOnly, $needsFollowUp, $ownedByMe, $ownerId, $perPage, $page)
            ->toArray();
    }

    public function getConversation(string $tenantId, string $leadId, int $perPage = 50, int $page = 1): array
    {
        return $this->repository->getConversationMessagesPaginated($tenantId, $leadId, $perPage, $page)->toArray();
    }

    public function markLeadMessagesAsRead(string $tenantId, string $leadId): void
    {
        $this->repository->markLeadMessagesAsRead($tenantId, $leadId);
        $this->leadRepository->resetUnreadCount($tenantId, $leadId);
    }
}
