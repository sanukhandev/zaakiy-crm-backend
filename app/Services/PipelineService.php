<?php

namespace App\Services;

use App\Repositories\LeadRepository;

class PipelineService
{
    public function __construct(protected LeadRepository $leadRepo) {}

    public function getPipeline(array $auth): array
    {
        return $this->leadRepo->getPipeline($auth['tenant_id']);
    }
}
