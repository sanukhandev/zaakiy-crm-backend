<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('tenant_id')->index();
            $table->string('type', 30);
            $table->text('content');
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table
                ->foreign('lead_id')
                ->references('id')
                ->on('leads')
                ->cascadeOnDelete();
            $table->index(['lead_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
    }
};
