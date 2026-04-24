<?php

namespace App\Services;

use App\Repositories\AnalyticsMetricRepository;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(
        protected AnalyticsMetricRepository $repository,
    ) {}

    public function aggregateAllTenants(): void
    {
        foreach ($this->repository->listTenantIds() as $tenantId) {
            $this->aggregateTenantMetrics((string) $tenantId);
        }
    }

    public function aggregateTenantMetrics(string $tenantId): void
    {
        $metricDate = now()->toDateString();

        $totalLeads = (int) DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->count();

        $wonLeads = (int) DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'won')
            ->count();

        $conversionRate = $totalLeads > 0
            ? round(($wonLeads / $totalLeads) * 100, 2)
            : 0.0;

        $this->repository->upsertMetric($tenantId, 'leads_count', $metricDate, $totalLeads);
        $this->repository->upsertMetric($tenantId, 'conversion_rate', $metricDate, $conversionRate);

        $sourceBreakdown = DB::table('leads')
            ->selectRaw("COALESCE(source, 'unknown') as source_key, COUNT(*) as aggregate_count")
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->groupBy('source_key')
            ->get();

        foreach ($sourceBreakdown as $row) {
            $this->repository->upsertMetric(
                $tenantId,
                'source_breakdown',
                $metricDate,
                (int) $row->aggregate_count,
                (string) $row->source_key,
            );
        }
    }
}