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

        if (!Schema::hasColumn('leads', 'stage_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->uuid('stage_id')->nullable()->after('status')->index();

                if (Schema::hasTable('pipeline_stages')) {
                    $table
                        ->foreign('stage_id')
                        ->references('id')
                        ->on('pipeline_stages')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('leads') || !Schema::hasColumn('leads', 'stage_id')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            try {
                $table->dropForeign(['stage_id']);
            } catch (\Throwable) {
                // Ignore when foreign key is not present.
            }

            $table->dropIndex(['stage_id']);
            $table->dropColumn('stage_id');
        });
    }
};
