<?php

namespace App\Services;

use App\Repositories\LeadActivityRepository;
use Illuminate\Support\Facades\DB;

class LeadActivityService
{
    public function __construct(
        protected LeadActivityRepository $repository,
    ) {}

    public function logInboundMessage(string $tenantId, string $leadId, string $content, ?string $createdBy = null): object
    {
        return $this->repository->create(
            $tenantId,
            $leadId,
            'message_inbound',
            'Incoming message: ' . trim($content),
            $createdBy,
        );
    }

    public function logOutboundMessage(
        string $leadId,
        string $tenantId,
        string $messageId,
        string $channel,
        string $content,
        ?string $createdBy = null
    ): object {
        return $this->repository->create(
            $tenantId,
            $leadId,
            'message_outbound',
            sprintf('Outgoing %s message: %s', $channel, trim($content)),
            $createdBy,
        );
    }

    public function logAssignment(string $tenantId, string $leadId, string $assignedToId, ?string $previousAssignedToId = null, ?string $createdBy = null): object
    {
        $content = $previousAssignedToId
            ? sprintf('Assignment changed from %s to %s', $previousAssignedToId, $assignedToId)
            : sprintf('Assigned to %s', $assignedToId);

        return $this->repository->create(
            $tenantId,
            $leadId,
            'assignment',
            $content,
            $createdBy,
        );
    }

    public function logStatusChange(string $tenantId, string $leadId, string $newStatus, ?string $previousStatus = null, ?string $createdBy = null): object
    {
        $content = $previousStatus
            ? sprintf('Status changed from %s to %s', $previousStatus, $newStatus)
            : sprintf('Status changed to %s', $newStatus);

        return $this->repository->create(
            $tenantId,
            $leadId,
            'status_change',
            $content,
            $createdBy,
        );
    }

    public function getLeadTimeline(string $tenantId, string $leadId, int $limit = 50, int $offset = 0): array
    {
        return $this->repository->findByLeadId($tenantId, $leadId, $limit, $offset);
    }

    public function countLeadActivities(string $tenantId, string $leadId): int
    {
        return $this->repository->countByLeadId($tenantId, $leadId);
    }

    public function logLeadCreated(string $tenantId, string $leadId, string $source, ?string $createdBy = null): object
    {
        return $this->repository->create(
            $tenantId,
            $leadId,
            'lead_created',
            sprintf('Lead created from %s', $source),
            $createdBy,
        );
    }

    public function logLeadUpdated(string $tenantId, string $leadId, string $source, ?string $createdBy = null): object
    {
        return $this->repository->create(
            $tenantId,
            $leadId,
            'lead_updated',
            sprintf('Lead updated from %s ingestion', $source),
            $createdBy,
        );
    }

    public function logStageChange(
        string $tenantId,
        string $leadId,
        string $newStageName,
        string $previousStageName,
        ?string $createdBy = null,
    ): object {
        return $this->repository->create(
            $tenantId,
            $leadId,
            'stage_changed',
            sprintf('Moved from %s to %s', $previousStageName, $newStageName),
            $createdBy,
        );
    }
}
