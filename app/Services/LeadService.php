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

    public function updateLead(array $auth, int $id, array $payload)
    {
        return $this->leadRepo->update($id, $auth, $payload);
    }

    public function deleteLead(array $auth, int $id): bool
    {
        return $this->leadRepo->delete($id, $auth['tenant_id']);
    }
}
