<?php

namespace App\Services;

use App\Repositories\LeadRepository;

class LeadService
{
    protected $leadRepo;

    public function __construct(LeadRepository $leadRepo)
    {
        $this->leadRepo = $leadRepo;
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

        $id = $this->leadRepo->create([
            'tenant_id' => $auth['tenant_id'],
            'name' => $payload['name'],
            'phone' => $payload['phone'] ?? null,
            'email' => $payload['email'] ?? null,
            'source' => $payload['source'] ?? null,
            'status' => $payload['status'] ?? 'new',
            'assigned_to' => $payload['assigned_to'] ?? $auth['user_id'],
            'metadata' => json_encode($payload['metadata'] ?? []),
        ]);

        return ['id' => $id];
    }

    public function listLeads(array $auth, array $params)
    {
        return $this->leadRepo->getPaginated($auth['tenant_id'], $params);
    }

    public function updateLead(array $auth, string $id, array $payload)
    {
        return $this->leadRepo->update($id, $auth, $payload);
    }

    public function deleteLead(array $auth, string $id): bool
    {
        return $this->leadRepo->delete($id, $auth['tenant_id']);
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
        return $this->leadRepo->bulkUpdate(
            $auth,
            $payload['lead_ids'],
            $payload,
        );
    }

    public function bulkAssignLeads(array $auth, array $payload): int
    {
        return $this->leadRepo->bulkAssign(
            $auth,
            $payload['lead_ids'],
            $payload['assigned_to'],
        );
    }

    public function bulkDeleteLeads(array $auth, array $payload): int
    {
        return $this->leadRepo->bulkDelete($auth, $payload['lead_ids']);
    }
}
