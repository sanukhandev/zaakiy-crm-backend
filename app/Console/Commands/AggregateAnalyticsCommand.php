<?php

namespace App\Console\Commands;

use App\Jobs\AggregateAnalyticsJob;
use Illuminate\Console\Command;

class AggregateAnalyticsCommand extends Command
{
    protected $signature = 'app:aggregate-analytics {tenantId?}';

    protected $description = 'Queue analytics aggregation for all tenants or a specific tenant';

    public function handle(): int
    {
        dispatch(new AggregateAnalyticsJob($this->argument('tenantId')));

        $this->info('Analytics aggregation queued.');

        return self::SUCCESS;
    }
}