<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('analytics_metrics')) {
            return;
        }

        Schema::create('analytics_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('metric_key', 64);
            $table->date('metric_date');
            $table->string('dimension', 128)->nullable();
            $table->decimal('metric_value', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'metric_key', 'metric_date', 'dimension'], 'analytics_metrics_unique');
            $table->index(['tenant_id', 'metric_key', 'metric_date']);
        });
    }

    public function down(): void
    {
        // Table is managed by Supabase after bootstrap. Do not drop automatically.
    }
};