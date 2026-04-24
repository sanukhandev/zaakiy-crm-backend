<?php

namespace App\Services;

use App\Events\LeadStageChanged;
use App\Repositories\PipelineRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class PipelineService
{
    public function __construct(
        protected PipelineRepository $pipelineRepository,
        protected LeadAutomationStateService $leadAutomationStateService,
        protected LeadActivityService $activityService,
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
        $tenantId   = $auth['tenant_id'];
        $stageId    = $payload['stage_id'];
        $position   = (int) ($payload['position'] ?? 0);
        $changedBy  = $auth['user_id'] ?? null;

        $leadBefore = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('id', $leadId)
            ->whereNull('deleted_at')
            ->first();

        if (!$leadBefore) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Lead not found');
        }

        $previousStage = $leadBefore->stage_id
            ? DB::table('pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->where('id', $leadBefore->stage_id)
                ->first()
            : null;

        $newStage = DB::table('pipeline_stages')
            ->where('tenant_id', $tenantId)
            ->where('id', $stageId)
            ->first();

        if (!$newStage) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Pipeline stage not found');
        }

        $result = $this->pipelineRepository->moveLeadToStage(
            $tenantId,
            $leadId,
            $stageId,
            $position,
            $changedBy,
        );

        // Conversion tracking: only set once, never overwrite
        $nameToken = strtolower(str_replace([' ', '-'], '_', trim((string) $newStage->name)));
        $conversionUpdates = [];

        if (in_array($nameToken, ['closed_won', 'won'], true) && empty($leadBefore->won_at)) {
            $conversionUpdates['won_at'] = now();
        }

        if (in_array($nameToken, ['closed_lost', 'lost'], true) && empty($leadBefore->lost_at)) {
            $conversionUpdates['lost_at'] = now();
        }

        if ($conversionUpdates !== []) {
            DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('id', $leadId)
                ->update(array_merge($conversionUpdates, ['updated_at' => now()]));
        }

        // Activity log
        $oldStageName = $previousStage ? (string) $previousStage->name : 'none';
        $newStageName = (string) $newStage->name;

        $this->activityService->logStageChange(
            tenantId: $tenantId,
            leadId: $leadId,
            newStageName: $newStageName,
            previousStageName: $oldStageName,
            createdBy: $changedBy,
        );

        // Fire event (automation engine listens here)
        $leadAfter = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('id', $leadId)
            ->first();

        Event::dispatch(new LeadStageChanged(
            lead: $leadAfter,
            previousStage: $previousStage,
            newStage: $newStage,
            tenantId: $tenantId,
        ));

        return $result;
    }
}
