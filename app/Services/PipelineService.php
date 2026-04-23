<?php

namespace App\Services;

use App\Repositories\PipelineRepository;

class PipelineService
{
    public function __construct(
        protected PipelineRepository $pipelineRepository,
        protected LeadAutomationStateService $leadAutomationStateService,
    ) {}

    public function getPipeline(array $auth): array
    {
        $stages = $this->pipelineRepository->getPipeline($auth['tenant_id']);

        return array_map(function (array $stage) use ($auth) {
            $stage['leads'] = $this->leadAutomationStateService->annotateLeadCollection(
                $auth['tenant_id'],
                $stage['leads'] ?? [],
            );

            return $stage;
        }, $stages);
    }

    public function createStage(array $auth, array $payload): array
    {
        return $this->pipelineRepository->createStage(
            $auth['tenant_id'],
            $payload,
        );
    }

    public function updateStage(array $auth, string $id, array $payload): array
    {
        return $this->pipelineRepository->updateStage(
            $auth['tenant_id'],
            $id,
            $payload,
        );
    }

    public function moveLeadToStage(
        array $auth,
        string $leadId,
        array $payload,
    ): array {
        return $this->pipelineRepository->moveLeadToStage(
            $auth['tenant_id'],
            $leadId,
            $payload['stage_id'],
            (int) $payload['position'],
            $auth['user_id'] ?? null,
        );
    }
}
