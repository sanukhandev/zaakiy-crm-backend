<?php

namespace App\Services;

use App\Repositories\AnalyticsMetricRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(
        protected AnalyticsMetricRepository $repository,
    ) {}

    public function getOverview(string $tenantId, array $filters = []): array
    {
        $today = now()->toDateString();
        $rangeStart = !empty($filters['date_from'])
            ? Carbon::parse((string) $filters['date_from'])->startOfDay()
            : now()->subDays(29)->startOfDay();
        $rangeEnd = !empty($filters['date_to'])
            ? Carbon::parse((string) $filters['date_to'])->endOfDay()
            : now()->endOfDay();

        $baseQuery = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);

        if (!empty($filters['source'])) {
            $baseQuery->where('source', (string) $filters['source']);
        }

        $rows = $baseQuery
            ->select(['status', 'source', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        $totalLeads = $rows->count();
        $qualifiedLeads = $rows->filter(fn ($lead) => in_array($lead->status, ['qualified', 'proposal', 'closed_won', 'won'], true))->count();
        $wonLeads = $rows->filter(fn ($lead) => in_array($lead->status, ['won', 'closed_won'], true))->count();
        $dailyLeadCount = $rows->filter(fn ($lead) => str_starts_with((string) $lead->created_at, $today))->count();
        $conversionRate = $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 2) : 0.0;

        $statusCounts = [];
        $sourceCounts = [];
        $trendMap = [];

        foreach ($rows as $lead) {
            $status = (string) $lead->status;
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            $source = $lead->source ? (string) $lead->source : 'unknown';
            $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;

            $dateKey = Carbon::parse((string) $lead->created_at)->toDateString();
            $trendMap[$dateKey] = ($trendMap[$dateKey] ?? 0) + 1;
        }

        $dailyTrend = [];
        $cursor = $rangeStart->copy()->startOfDay();
        $trendEnd = $rangeEnd->copy()->startOfDay();

        while ($cursor->lte($trendEnd)) {
            $key = $cursor->toDateString();
            $dailyTrend[] = [
                'date' => $key,
                'label' => $cursor->format('M j'),
                'leads' => (int) ($trendMap[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        $chartData = collect($statusCounts)
            ->map(fn ($value, $status) => ['status' => $status, 'value' => (int) $value])
            ->values()
            ->all();

        $sourceChartData = collect($sourceCounts)
            ->map(fn ($value, $source) => ['source' => $source, 'value' => (int) $value])
            ->values()
            ->all();

        $stagePerformance = collect($statusCounts)
            ->map(fn ($value, $status) => [
                'status' => $status,
                'value' => (int) $value,
                'share' => $totalLeads > 0 ? round(((int) $value / $totalLeads) * 100, 1) : 0,
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        $topSources = collect($sourceCounts)
            ->map(fn ($value, $source) => [
                'source' => $source,
                'value' => (int) $value,
                'share' => $totalLeads > 0 ? round(((int) $value / $totalLeads) * 100, 1) : 0,
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        return [
            'overview' => [
                'date' => $today,
                'total_leads' => $totalLeads,
                'qualified_leads' => $qualifiedLeads,
                'won_leads' => $wonLeads,
                'daily_lead_count' => $dailyLeadCount,
                'conversion_rate' => $conversionRate,
                'source_breakdown' => $sourceCounts,
            ],
            'chartData' => $chartData,
            'sourceChartData' => $sourceChartData,
            'dailyTrend' => $dailyTrend,
            'stagePerformance' => $stagePerformance,
            'topSources' => $topSources,
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