<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyticsMetricRepository
{
    public function upsertMetric(
        string $tenantId,
        string $metricKey,
        string $metricDate,
        float|int $metricValue,
        ?string $dimension = null,
        array $metadata = [],
    ): void {
        $exists = DB::table('analytics_metrics')
            ->where('tenant_id', $tenantId)
            ->where('metric_key', $metricKey)
            ->where('metric_date', $metricDate)
            ->where('dimension', $dimension)
            ->first();

        $payload = [
            'metric_value' => $metricValue,
            'metadata' => $metadata === [] ? null : json_encode($metadata),
            'updated_at' => now(),
        ];

        if ($exists) {
            DB::table('analytics_metrics')
                ->where('tenant_id', $tenantId)
                ->where('metric_key', $metricKey)
                ->where('metric_date', $metricDate)
                ->where('dimension', $dimension)
                ->update($payload);

            return;
        }

        DB::table('analytics_metrics')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'metric_key' => $metricKey,
            'metric_date' => $metricDate,
            'dimension' => $dimension,
            'metric_value' => $metricValue,
            'metadata' => $payload['metadata'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function listTenantIds(): array
    {
        return DB::table('leads')
            ->select('tenant_id')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('tenant_id')
            ->filter()
            ->values()
            ->all();
    }
}