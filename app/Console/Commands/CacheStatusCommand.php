<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\CacheHelper;

class CacheStatusCommand extends Command
{
    protected $signature = 'cache:status';
    protected $description = 'Display API cache status and settings';

    public function handle()
    {
        $this->info('=== API Cache Status ===');
        $this->newLine();

        $enabled = CacheHelper::isEnabled();
        $ttl = CacheHelper::getTTL();
        $store = config('cache.default');

        $this->line('Caching Enabled: ' . ($enabled ? '✓ Yes' : '✗ No'));
        $this->line('Cache Store: ' . $store);
        $this->line('Cache TTL: ' . $ttl . ' seconds (' . round($ttl / 60, 1) . ' minutes)');

        $this->newLine();
        $this->info('To change settings, edit .env file:');
        $this->line('  API_CACHE_ENABLED=true|false');
        $this->line('  API_CACHE_TTL=300');

        $this->newLine();

        if (!$enabled) {
            $this->warn('⚠️  API caching is currently DISABLED. This may impact performance.');
        }
    }
}
