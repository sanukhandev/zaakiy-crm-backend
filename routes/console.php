<?php

use App\Support\SchemaCompatibilityChecker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:schema-check', function () {
    $result = app(SchemaCompatibilityChecker::class)->check();

    if (!empty($result['errors'])) {
        $this->error('Schema compatibility errors:');
        foreach ($result['errors'] as $error) {
            $this->line('- ' . $error);
        }
    }

    if (!empty($result['warnings'])) {
        $this->warn('Schema compatibility warnings:');
        foreach ($result['warnings'] as $warning) {
            $this->line('- ' . $warning);
        }
    }

    if (empty($result['errors']) && empty($result['warnings'])) {
        $this->info('Schema compatibility check passed with no issues.');
    }

    return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
})->purpose('Validate DB schema compatibility against application requirements');
