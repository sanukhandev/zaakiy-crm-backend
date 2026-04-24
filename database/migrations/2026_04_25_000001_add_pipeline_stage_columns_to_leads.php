<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'pipeline_stage_id')) {
                $table->uuid('pipeline_stage_id')->nullable()->after('status');
                $table->index(['tenant_id', 'pipeline_stage_id'], 'leads_tenant_stage_idx');
            }

            if (!Schema::hasColumn('leads', 'won_at')) {
                $table->timestamp('won_at')->nullable()->after('pipeline_stage_id');
            }

            if (!Schema::hasColumn('leads', 'lost_at')) {
                $table->timestamp('lost_at')->nullable()->after('won_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'pipeline_stage_id')) {
                $table->dropIndex('leads_tenant_stage_idx');
                $table->dropColumn('pipeline_stage_id');
            }

            if (Schema::hasColumn('leads', 'won_at')) {
                $table->dropColumn('won_at');
            }

            if (Schema::hasColumn('leads', 'lost_at')) {
                $table->dropColumn('lost_at');
            }
        });
    }
};
