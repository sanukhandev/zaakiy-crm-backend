<?php

namespace App\Services;

use App\Repositories\LeadRepository;
use App\Repositories\PipelineRepository;
use App\Repositories\UserAssignmentRepository;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function __construct(
        protected LeadRepository $leadRepo,
        protected PipelineRepository $pipelineRepository,
        protected UserAssignmentRepository $userAssignmentRepository,
    ) {}

    private function resolveAutoAssignee(string $tenantId): ?string
    {
        return $this->userAssignmentRepository->findLeastLoadedSalesUserId($tenantId);
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
        $assignedTo = $payload['assigned_to'] ?? $this->resolveAutoAssignee($tenantId);
        $stageId = $payload['stage_id'] ?? $this->pipelineRepository->getFirstStageId($tenantId);

        $id = DB::transaction(function () use ($tenantId, $payload, $assignedTo, $stageId) {
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

            return $leadId;
        });

        $this->pipelineRepository->forgetCache($tenantId);

        return ['id' => $id];
    }

    public function createOrUpdateLeadFromWebhook(
        string $tenantId,
        array $payload,
    ): array {
        $assignedTo = $this->resolveAutoAssignee($tenantId);
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

        $this->pipelineRepository->forgetCache($tenantId);

        return $result;
    }

    public function listLeads(array $auth, array $params)
    {
        return $this->leadRepo->getPaginated($auth['tenant_id'], $params);
    }

    public function updateLead(array $auth, string $id, array $payload)
    {
        $result = $this->leadRepo->update($id, $auth, $payload);
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
        $result = $this->leadRepo->delete($id, $auth['tenant_id']);
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
        $affected = $this->leadRepo->bulkUpdate(
            $auth,
            $payload['lead_ids'],
            $payload,
        );

        $this->pipelineRepository->forgetCache($auth['tenant_id']);

        return $affected;
    }

    public function bulkAssignLeads(array $auth, array $payload): int
    {
        $affected = $this->leadRepo->bulkAssign(
            $auth,
            $payload['lead_ids'],
            $payload['assigned_to'],
        );

        $this->pipelineRepository->forgetCache($auth['tenant_id']);

        return $affected;
    }

    public function bulkDeleteLeads(array $auth, array $payload): int
    {
        $affected = $this->leadRepo->bulkDelete($auth, $payload['lead_ids']);
        $this->pipelineRepository->forgetCache($auth['tenant_id']);

        return $affected;
    }
}
