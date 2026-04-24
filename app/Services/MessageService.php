<?php

namespace App\Services;

use App\Repositories\MessageRepository;

class MessageService
{
    public function __construct(
        protected MessageRepository $repository,
    ) {}

    public function createInboundMessage(
        string $tenantId,
        string $leadId,
        string $channel,
        string $content,
        ?string $externalId = null,
    ): object {
        return $this->repository->create(
            $tenantId,
            $leadId,
            $channel,
            'inbound',
            $content,
            $externalId,
        );
    }

    public function createOutboundMessage(
        string $tenantId,
        string $leadId,
        string $channel,
        string $content,
        ?string $externalId = null,
        ?string $createdBy = null,
    ): object {
        return $this->repository->create(
            $tenantId,
            $leadId,
            $channel,
            'outbound',
            $content,
            $externalId,
            $createdBy,
        );
    }

    public function getLeadMessages(string $tenantId, string $leadId, int $limit = 50, int $offset = 0): array
    {
        return $this->repository->findByLeadId($tenantId, $leadId, $limit, $offset);
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
        return $this->repository->updateStatus($tenantId, $messageId, $status);
    }

    public function updateMessageStatusByExternalId(string $tenantId, string $externalId, string $status): bool
    {
        return $this->repository->updateStatusByExternalId($tenantId, $externalId, $status);
    }

    public function getInbox(
        string $tenantId,
        ?string $assignedTo = null,
        bool $unreadOnly = false,
        bool $needsFollowUp = false,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->repository->getInboxMessages($tenantId, $assignedTo, $unreadOnly, $needsFollowUp, $limit, $offset);
    }

    public function getConversation(string $tenantId, string $leadId, int $limit = 100): array
    {
        return $this->repository->getConversationMessages($tenantId, $leadId, $limit);
    }
}
