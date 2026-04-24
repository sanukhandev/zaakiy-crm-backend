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
            json_encode(['message' => $content]),
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
            json_encode([
                'message' => $content,
                'channel' => $channel,
                'message_id' => $messageId,
            ]),
            $createdBy,
        );
    }

    public function logAssignment(string $tenantId, string $leadId, string $assignedToId, ?string $previousAssignedToId = null, ?string $createdBy = null): object
    {
        $content = json_encode([
            'assigned_to' => $assignedToId,
            'previous_assigned_to' => $previousAssignedToId,
        ]);

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
        $content = json_encode([
            'new_status' => $newStatus,
            'previous_status' => $previousStatus,
        ]);

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
}
