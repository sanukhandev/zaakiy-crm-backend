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
        return $this->getRepo()->create([
            'tenant_id' => $auth['tenant_id'],
            'name' => $payload['name'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'email' => $payload['email'] ?? null,
            'source' => $payload['source'] ?? 'website',
            'status' => 'new',
            'metadata' => json_encode($payload['metadata'] ?? []),
        ]);
    }

    public function listLeads($auth, $params)
    {
        return $this->leadRepo->getPaginated($auth['tenant_id'], $params);
    }

    public function updateLead($auth, $id, $payload)
    {
        return $this->getRepo()->update($id, $auth['tenant_id'], $payload);
    }
}
