<?php

namespace App\Services;

use App\Repositories\LeadRepository;

class LeadService
{
    protected $leadRepo;

    public function __construct()
    {
        $this->leadRepo = new LeadRepository();
    }

    public function createLead($auth, $payload)
    {
        return $this->leadRepo->create([
            'tenant_id' => $auth['tenant_id'],
            'name' => $payload['name'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'email' => $payload['email'] ?? null,
            'source' => $payload['source'] ?? 'website',
            'status' => 'new',
            'metadata' => json_encode($payload['metadata'] ?? [])
        ]);
    }

    public function listLeads($auth)
    {
        return $this->leadRepo->getAll($auth['tenant_id']);
    }

    public function updateLead($auth, $id, $payload)
    {
        return $this->leadRepo->update(
            $id,
            $auth['tenant_id'],
            $payload
        );
    }
}