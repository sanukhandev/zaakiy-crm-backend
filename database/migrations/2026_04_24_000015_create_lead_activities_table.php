<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Table already exists in Supabase as 'lead_activities', no migration needed
        if (!Schema::hasTable('lead_activities')) {
            Schema::create('lead_activities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('lead_id');
                $table->string('type', 32); // message_inbound, message_outbound, assignment, status_change
                $table->text('content')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'lead_id', 'created_at']);
                $table->index(['tenant_id', 'type']);
                $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        // Don't drop - table is managed by Supabase
    }
};
