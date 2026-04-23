<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_automation_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->unique();
            $table->boolean('auto_assignment_enabled')->default(true);
            $table->string('assignment_strategy', 32)->default('least_load');
            $table->uuid('round_robin_last_user_id')->nullable();
            $table->boolean('auto_reply_enabled')->default(false);
            $table->text('auto_reply_template')->nullable();
            $table->unsignedInteger('follow_up_threshold_minutes')->default(60);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'auto_assignment_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_automation_settings');
    }
};
