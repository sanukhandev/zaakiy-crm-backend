<?php

namespace App\Services;

use App\Repositories\AnalyticsMetricRepository;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(
        protected AnalyticsMetricRepository $repository,
    ) {}

    public function getOverview(string $tenantId): array
    {
        $metricDate = now()->toDateString();

        $metrics = DB::table('analytics_metrics')
            ->where('tenant_id', $tenantId)
            ->where('metric_date', $metricDate)
            ->get()
            ->keyBy(fn ($row) => $row->metric_key . ':' . ($row->dimension ?? ''));

        $get = fn (string $key, ?string $dim = null) => (float) ($metrics[$key . ':' . ($dim ?? '')]->metric_value ?? 0);

        $sourceBreakdown = DB::table('analytics_metrics')
            ->where('tenant_id', $tenantId)
            ->where('metric_key', 'source_breakdown')
            ->where('metric_date', $metricDate)
            ->whereNotNull('dimension')
            ->pluck('metric_value', 'dimension')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        return [
            'date'              => $metricDate,
            'total_leads'       => (int) $get('leads_count'),
            'qualified_leads'   => (int) $get('qualified_leads'),
            'won_leads'         => (int) $get('won_leads'),
            'daily_lead_count'  => (int) $get('daily_lead_count'),
            'conversion_rate'   => (float) $get('conversion_rate'),
            'source_breakdown'  => $sourceBreakdown,
        ];
    }

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
            ->whereIn('status', ['won', 'closed_won'])
            ->count();

        // Qualified = status is 'qualified' OR pipeline_stage_id references a stage named 'Qualified'/'Proposal'
        $qualifiedLeads = (int) DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['qualified', 'proposal', 'closed_won', 'won'])
            ->count();

        // Daily lead count = leads created today
        $dailyLeadCount = (int) DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereDate('created_at', $metricDate)
            ->count();

        $conversionRate = $totalLeads > 0
            ? round(($wonLeads / $totalLeads) * 100, 2)
            : 0.0;

        $this->repository->upsertMetric($tenantId, 'leads_count', $metricDate, $totalLeads);
        $this->repository->upsertMetric($tenantId, 'won_leads', $metricDate, $wonLeads);
        $this->repository->upsertMetric($tenantId, 'qualified_leads', $metricDate, $qualifiedLeads);
        $this->repository->upsertMetric($tenantId, 'daily_lead_count', $metricDate, $dailyLeadCount);
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