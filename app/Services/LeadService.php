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

    private function getRepo()
    {
        if (!$this->leadRepo) {
            $this->leadRepo = new LeadRepository();
        }
        return $this->leadRepo;
    }

    public function createLead($auth, $payload)
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

    public function listLeads($auth, $params)
    {
        return $this->leadRepo->getPaginated($auth['tenant_id'], $params);
    }

    public function updateLead($auth, $id, $payload)
    {
        return $this->getRepo()->update($id, $auth['tenant_id'], $payload);
    }

    public function deleteLead($auth, $id)
    {
        return $this->getRepo()->delete($id, $auth['tenant_id']);
    }
}
