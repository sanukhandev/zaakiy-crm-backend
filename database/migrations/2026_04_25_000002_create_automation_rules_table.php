<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('automation_rules')) {
            return;
        }

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('trigger_type', 64); // lead_created | message_received | stage_changed
            $table->jsonb('conditions')->default('{}');
            $table->jsonb('actions')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'trigger_type', 'is_active'], 'automation_rules_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
