<?php

namespace App\Repositories;

use App\Support\CacheHelper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PipelineRepository
{
    private const DEFAULT_STAGES = [
        'New',
        'Contacted',
        'Qualified',
        'Proposal',
        'Closed Won',
        'Closed Lost',
    ];

    private function cacheKey(string $tenantId): string
    {
        return 'pipelines:tenant:' . $tenantId;
    }

    public function forgetCache(string $tenantId): void
    {
        if (!CacheHelper::isEnabled()) {
            return;
        }

        Cache::forget($this->cacheKey($tenantId));
    }

    public function ensureDefaultStages(string $tenantId): void
    {
        $exists = DB::table('pipeline_stages')
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        $rows = collect(self::DEFAULT_STAGES)
            ->values()
            ->map(fn (string $name, int $index) => [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => $name,
                'order_index' => $index,
                'created_at' => $now,
            ])
            ->all();

        DB::table('pipeline_stages')->insert($rows);
    }

    public function getFirstStageId(string $tenantId): ?string
    {
        $this->ensureDefaultStages($tenantId);

        $stage = DB::table('pipeline_stages')
            ->where('tenant_id', $tenantId)
            ->orderBy('order_index')
            ->first(['id']);

        return $stage?->id;
    }

    private function normalizeToken(?string $value): string
    {
        if (!$value) {
            return '';
        }

        return strtolower(str_replace([' ', '-'], '_', trim($value)));
    }

    public function getPipeline(string $tenantId): array
    {
        $this->ensureDefaultStages($tenantId);

        return CacheHelper::remember($this->cacheKey($tenantId), function () use ($tenantId) {
            $stages = DB::table('pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->orderBy('order_index')
                ->get();

            $leadRows = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->orderBy('position')
                ->get();

            $stageNameToId = [];
            foreach ($stages as $stage) {
                $stageNameToId[$this->normalizeToken($stage->name)] = $stage->id;
            }

            $groupedLeads = [];
            foreach ($leadRows as $lead) {
                $targetStageId = $lead->stage_id;

                if (!$targetStageId) {
                    $statusToken = $this->normalizeToken((string) ($lead->status ?? ''));

                    if ($statusToken === 'won') {
                        $statusToken = 'closed_won';
                    } elseif ($statusToken === 'lost') {
                        $statusToken = 'closed_lost';
                    }

                    $targetStageId = $stageNameToId[$statusToken] ?? null;
                }

                if (!$targetStageId) {
                    $fallback = $stages->first();
                    $targetStageId = $fallback?->id;
                }

                if ($targetStageId) {
                    // Convert to array to avoid caching incomplete objects
                    $groupedLeads[$targetStageId][] = (array) $lead;
                }
            }

            $result = [];
            foreach ($stages as $stage) {
                $result[] = [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'order_index' => (int) $stage->order_index,
                    'leads' => array_map(fn($lead) => (object) $lead, $groupedLeads[$stage->id] ?? []),
                ];
            }

            return $result;
        });
    }

    public function createStage(string $tenantId, array $payload): array
    {
        return DB::transaction(function () use ($tenantId, $payload) {
            $count = (int) DB::table('pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->count();

            $targetOrder = min(max((int) ($payload['order_index'] ?? $count), 0), $count);

            DB::table('pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->where('order_index', '>=', $targetOrder)
                ->update([
                    'order_index' => DB::raw('order_index + 1'),
                ]);

            $id = (string) Str::uuid();

            DB::table('pipeline_stages')->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => $payload['name'],
                'order_index' => $targetOrder,
                'created_at' => now(),
            ]);

            $this->forgetCache($tenantId);

            return [
                'id' => $id,
                'order_index' => $targetOrder,
            ];
        });
    }

    public function updateStage(string $tenantId, string $stageId, array $payload): array
    {
        return DB::transaction(function () use ($tenantId, $stageId, $payload) {
            $stage = DB::table('pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->where('id', $stageId)
                ->lockForUpdate()
                ->first();

            if (!$stage) {
                throw new ModelNotFoundException('Pipeline stage not found');
            }

            $updates = [];

            if (array_key_exists('name', $payload)) {
                $updates['name'] = $payload['name'];
            }

            if (array_key_exists('order_index', $payload)) {
                $current = (int) $stage->order_index;
                $max = (int) DB::table('pipeline_stages')
                    ->where('tenant_id', $tenantId)
                    ->count() - 1;
                $target = min(max((int) $payload['order_index'], 0), $max);

                if ($target > $current) {
                    DB::table('pipeline_stages')
                        ->where('tenant_id', $tenantId)
                        ->where('id', '!=', $stageId)
                        ->whereBetween('order_index', [$current + 1, $target])
                        ->update([
                            'order_index' => DB::raw('order_index - 1'),
                        ]);
                } elseif ($target < $current) {
                    DB::table('pipeline_stages')
                        ->where('tenant_id', $tenantId)
                        ->where('id', '!=', $stageId)
                        ->whereBetween('order_index', [$target, $current - 1])
                        ->update([
                            'order_index' => DB::raw('order_index + 1'),
                        ]);
                }

                $updates['order_index'] = $target;
            }

            if (!empty($updates)) {
                DB::table('pipeline_stages')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $stageId)
                    ->update($updates);
            }

            $this->forgetCache($tenantId);

            return [
                'id' => $stageId,
                'name' => $updates['name'] ?? $stage->name,
                'order_index' => (int) ($updates['order_index'] ?? $stage->order_index),
            ];
        });
    }

    public function moveLeadToStage(
        string $tenantId,
        string $leadId,
        string $targetStageId,
        int $targetPosition,
        ?string $changedBy,
    ): array {
        return DB::transaction(function () use ($tenantId, $leadId, $targetStageId, $targetPosition, $changedBy) {
            $lead = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('id', $leadId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$lead) {
                throw new ModelNotFoundException('Lead not found');
            }

            $targetStage = DB::table('pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->where('id', $targetStageId)
                ->first();

            if (!$targetStage) {
                throw new ModelNotFoundException('Pipeline stage not found');
            }

            $oldStageId = $lead->stage_id;
            $oldPosition = (int) ($lead->position ?? 0);

            $targetCount = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('stage_id', $targetStageId)
                ->when($oldStageId === $targetStageId, function ($query) use ($leadId) {
                    $query->where('id', '!=', $leadId);
                })
                ->count();

            $clampedTargetPosition = min(max($targetPosition, 0), (int) $targetCount);

            if ($oldStageId === $targetStageId) {
                if ($clampedTargetPosition > $oldPosition) {
                    DB::table('leads')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->where('stage_id', $oldStageId)
                        ->where('id', '!=', $leadId)
                        ->whereBetween('position', [$oldPosition + 1, $clampedTargetPosition])
                        ->update([
                            'position' => DB::raw('position - 1'),
                            'updated_at' => now(),
                        ]);
                } elseif ($clampedTargetPosition < $oldPosition) {
                    DB::table('leads')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->where('stage_id', $oldStageId)
                        ->where('id', '!=', $leadId)
                        ->whereBetween('position', [$clampedTargetPosition, $oldPosition - 1])
                        ->update([
                            'position' => DB::raw('position + 1'),
                            'updated_at' => now(),
                        ]);
                }
            } else {
                DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('stage_id', $oldStageId)
                    ->where('id', '!=', $leadId)
                    ->where('position', '>', $oldPosition)
                    ->update([
                        'position' => DB::raw('position - 1'),
                        'updated_at' => now(),
                    ]);

                DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('stage_id', $targetStageId)
                    ->where('position', '>=', $clampedTargetPosition)
                    ->update([
                        'position' => DB::raw('position + 1'),
                        'updated_at' => now(),
                    ]);
            }

            DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('id', $leadId)
                ->update([
                    'stage_id' => $targetStageId,
                    'position' => $clampedTargetPosition,
                    'status' => strtolower(str_replace(' ', '_', $targetStage->name)),
                    'updated_at' => now(),
                ]);

            DB::table('lead_status_history')->insert([
                'id' => (string) Str::uuid(),
                'lead_id' => $leadId,
                'old_status' => $lead->status,
                'new_status' => strtolower(str_replace(' ', '_', $targetStage->name)),
                'changed_by' => $changedBy,
                'created_at' => now(),
            ]);

            $this->forgetCache($tenantId);

            return [
                'id' => $leadId,
                'stage_id' => $targetStageId,
                'position' => $clampedTargetPosition,
            ];
        });
    }
}
