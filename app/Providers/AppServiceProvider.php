<?php

namespace App\Providers;

use App\Support\SchemaCompatibilityChecker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!config('app.schema_compat_check_on_boot', true)) {
            return;
        }

        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $result = app(SchemaCompatibilityChecker::class)->check();

        if (!empty($result['errors'])) {
            Log::error('Schema compatibility check failed', [
                'errors' => $result['errors'],
                'warnings' => $result['warnings'] ?? [],
            ]);

            if (config('app.schema_compat_fail_hard', false)) {
                throw new \RuntimeException(
                    'Schema compatibility check failed: ' .
                        implode('; ', $result['errors']),
                );
            }
        }

        if (!empty($result['warnings'])) {
            Log::warning('Schema compatibility warnings', [
                'warnings' => $result['warnings'],
            ]);
        }
    }
}
