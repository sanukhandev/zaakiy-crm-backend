<?php

namespace App\Services;

use App\Repositories\LeadRepository;
use App\Repositories\MessageRepository;
use App\Repositories\PipelineRepository;
use App\Repositories\UserAssignmentRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function __construct(
        protected LeadRepository $leadRepo,
        protected MessageRepository $messageRepository,
        protected PipelineRepository $pipelineRepository,
        protected UserAssignmentRepository $userAssignmentRepository,
        protected AssignmentStrategyService $assignmentStrategyService,
        protected LeadAutomationStateService $leadAutomationStateService,
        protected LeadActivityService $leadActivityService,
    ) {}

    private function adjustAssignmentLoad(string $tenantId, ?string $previousUserId, ?string $nextUserId, int $count = 1): void
    {
        if ($previousUserId && $previousUserId !== $nextUserId) {
            $this->userAssignmentRepository->decrementCurrentLoad($tenantId, $previousUserId, $count);
        }

        if ($nextUserId && $nextUserId !== $previousUserId) {
            $this->userAssignmentRepository->incrementCurrentLoad($tenantId, $nextUserId, $count);
        }
    }

    private function adjustBulkAssignmentLoad(string $tenantId, array $previousAssignments, ?string $nextUserId): void
    {
        $countsByUser = [];
        $nextAssignments = 0;

        foreach ($previousAssignments as $assignedUserId) {
            if ($assignedUserId === $nextUserId) {
                continue;
            }

            if ($assignedUserId) {
                $countsByUser[$assignedUserId] = ($countsByUser[$assignedUserId] ?? 0) + 1;
            }

            if ($nextUserId) {
                $nextAssignments++;
            }
        }

        foreach ($countsByUser as $userId => $count) {
            $this->userAssignmentRepository->decrementCurrentLoad($tenantId, $userId, $count);
        }

        if ($nextUserId && $nextAssignments > 0) {
            $this->userAssignmentRepository->incrementCurrentLoad($tenantId, $nextUserId, $nextAssignments);
        }
    }

    public function createLead(array $auth, array $payload)
    {
        $duplicate = $this->leadRepo->findDuplicate(
            $auth['tenant_id'],
            $payload,
        );

        if ($duplicate) {
            return [
                'duplicate' => true,
                'data' => $duplicate,
            ];
        }

        $tenantId = $auth['tenant_id'];
        $assignedTo = array_key_exists('assigned_to', $payload)
            ? $payload['assigned_to']
            : $this->assignmentStrategyService->resolveUserId($tenantId);
        $stageId = $payload['stage_id'] ?? $this->pipelineRepository->getFirstStageId($tenantId);

        $id = DB::transaction(function () use ($tenantId, $payload, $assignedTo, $stageId, $auth) {
            $leadId = $this->leadRepo->create([
                'tenant_id' => $tenantId,
                'name' => $payload['name'],
                'phone' => $payload['phone'] ?? null,
                'email' => $payload['email'] ?? null,
                'source' => $payload['source'] ?? null,
                'status' => $payload['status'] ?? 'new',
                'stage_id' => $stageId,
                'assigned_to' => $assignedTo,
                'metadata' => json_encode($payload['metadata'] ?? []),
            ]);

            if ($assignedTo) {
                $this->userAssignmentRepository->incrementCurrentLoad($tenantId, $assignedTo);
            }

            $this->leadActivityService->logLeadCreated(
                $tenantId,
                $leadId,
                (string) ($payload['source'] ?? 'manual'),
                $auth['user_id'] ?? null,
            );

            if ($assignedTo) {
                $this->leadActivityService->logAssignment(
                    $tenantId,
                    $leadId,
                    $assignedTo,
                    null,
                    $auth['user_id'] ?? null,
                );
            }

            return $leadId;
        });

        $this->pipelineRepository->forgetCache($tenantId);

        return ['id' => $id];
    }

    public function createOrUpdateLeadFromWebhook(
        string $tenantId,
        array $payload,
    ): array {
        $assignedTo = $this->assignmentStrategyService->resolveUserId($tenantId);
        $stageId = $this->pipelineRepository->getFirstStageId($tenantId);

        $result = $this->leadRepo->createOrUpdateFromWebhook(
            $tenantId,
            $payload,
            $assignedTo,
            $stageId,
        );

        if (($result['action'] ?? null) === 'created' && $assignedTo) {
            $this->userAssignmentRepository->incrementCurrentLoad($tenantId, $assignedTo);
        }

        if (($result['action'] ?? null) === 'created') {
            $this->leadActivityService->logLeadCreated($tenantId, $result['id'], (string) ($payload['source'] ?? 'webhook'));

            if ($assignedTo) {
                $this->leadActivityService->logAssignment($tenantId, $result['id'], $assignedTo);
            }
        } elseif (($result['action'] ?? null) === 'updated') {
            $this->leadActivityService->logLeadUpdated($tenantId, $result['id'], (string) ($payload['source'] ?? 'webhook'));
        }

        $this->pipelineRepository->forgetCache($tenantId);

        return $result;
    }

    public function getLead(array $auth, string $id): ?object
    {
        $lead = $this->leadRepo->findByIdForTenant($id, $auth['tenant_id']);

        return $lead
            ? $this->leadAutomationStateService->annotateLead($auth['tenant_id'], $lead)
            : null;
    }

    public function getLeadMessages(array $auth, string $leadId, int $perPage = 50): array
    {
        $lead = $this->leadRepo->findByIdForTenant($leadId, $auth['tenant_id']);
        if (!$lead) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Lead not found');
        }

        return $this->messageRepository->findByLeadId($auth['tenant_id'], $leadId, $perPage);
    }

    public function listLeads(array $auth, array $params)
    {
        $paginator = $this->leadRepo->getPaginated($auth['tenant_id'], $params);
        if ($paginator instanceof LengthAwarePaginator) {
            $annotated = $this->leadAutomationStateService->annotateLeadCollection(
                $auth['tenant_id'],
                $paginator->items(),
            );
            $paginator->setCollection(collect($annotated));
        }

        return $paginator;
    }

    public function updateLead(array $auth, string $id, array $payload)
    {
        $existingLead = $this->leadRepo->findByIdForTenant($id, $auth['tenant_id']);
        if (!$existingLead) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Lead not found');
        }

        $result = $this->leadRepo->update($id, $auth, $payload);

        if (array_key_exists('assigned_to', $payload)) {
            $this->leadActivityService->logAssignment(
                $auth['tenant_id'],
                $id,
                (string) ($payload['assigned_to'] ?? 'unassigned'),
                $existingLead->assigned_to,
                $auth['user_id'] ?? null,
            );

            $this->adjustAssignmentLoad(
                $auth['tenant_id'],
                $existingLead->assigned_to,
                $payload['assigned_to'],
            );
        }

        if (array_key_exists('status', $payload) && $payload['status'] !== $existingLead->status) {
            $this->leadActivityService->logStatusChange(
                $auth['tenant_id'],
                $id,
                $payload['status'],
                $existingLead->status,
                $auth['user_id'] ?? null,
            );
        }

        $this->pipelineRepository->forgetCache($auth['tenant_id']);

        return $result;
    }

    public function moveLead(string $id, array $auth, array $payload)
    {
        $result = $this->leadRepo->moveLead($id, $auth, $payload);
        $this->pipelineRepository->forgetCache($auth['tenant_id']);

        return $result;
    }

    public function deleteLead(array $auth, string $id): bool
    {
        $existingLead = $this->leadRepo->findByIdForTenant($id, $auth['tenant_id']);
        if (!$existingLead) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Lead not found');
        }

        $result = $this->leadRepo->delete($id, $auth['tenant_id']);
        $this->adjustAssignmentLoad($auth['tenant_id'], $existingLead->assigned_to, null);
        $this->pipelineRepository->forgetCache($auth['tenant_id']);

        return $result;
    }

    public function addLeadActivity(
        array $auth,
        string $leadId,
        array $payload,
    ): array {
        $id = $this->leadRepo->addActivity($leadId, $auth, $payload);

        return ['id' => $id];
    }

    public function listLeadActivities(
        array $auth,
        string $leadId,
        array $params,
    ) {
        $perPage = (int) ($params['per_page'] ?? 20);

        return $this->leadRepo->listActivities(
            $leadId,
            $auth['tenant_id'],
            $perPage,
        );
    }

    public function bulkUpdateLeads(array $auth, array $payload): int
    {
        $previousAssignments = array_key_exists('assigned_to', $payload)
            ? $this->leadRepo->getAssignedUsersForLeadIds($auth['tenant_id'], $payload['lead_ids'])
            : [];

        $affected = $this->leadRepo->bulkUpdate(
            $auth,
            $payload['lead_ids'],
            $payload,
        );

        if (array_key_exists('assigned_to', $payload)) {
            $this->adjustBulkAssignmentLoad($auth['tenant_id'], $previousAssignments, $payload['assigned_to']);
        }

        $this->pipelineRepository->forgetCache($auth['tenant_id']);

        return $affected;
    }

    public function bulkAssignLeads(array $auth, array $payload): int
    {
        $previousAssignments = $this->leadRepo->getAssignedUsersForLeadIds($auth['tenant_id'], $payload['lead_ids']);

        $affected = $this->leadRepo->bulkAssign(
            $auth,
            $payload['lead_ids'],
            $payload['assigned_to'],
        );

        $this->adjustBulkAssignmentLoad($auth['tenant_id'], $previousAssignments, $payload['assigned_to']);
        $this->pipelineRepository->forgetCache($auth['tenant_id']);

        return $affected;
    }

    public function bulkDeleteLeads(array $auth, array $payload): int
    {
        $previousAssignments = $this->leadRepo->getAssignedUsersForLeadIds($auth['tenant_id'], $payload['lead_ids']);
        $affected = $this->leadRepo->bulkDelete($auth, $payload['lead_ids']);
        $this->adjustBulkAssignmentLoad($auth['tenant_id'], $previousAssignments, null);
        $this->pipelineRepository->forgetCache($auth['tenant_id']);

        return $affected;
    }
}
