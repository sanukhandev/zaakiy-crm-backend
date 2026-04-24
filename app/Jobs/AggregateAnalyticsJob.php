<?php

namespace App\Jobs;

use App\Services\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AggregateAnalyticsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        protected ?string $tenantId = null,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(AnalyticsService $analyticsService): void
    {
        if ($this->tenantId) {
            $analyticsService->aggregateTenantMetrics($this->tenantId);
            return;
        }

        $analyticsService->aggregateAllTenants();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Analytics aggregation job failed', [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}