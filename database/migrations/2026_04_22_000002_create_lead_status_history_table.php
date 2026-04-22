<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_status_history', function (Blueprint $table) {
            $table
                ->uuid('id')
                ->primary()
                ->default(DB::raw('gen_random_uuid()'));
            $table->uuid('lead_id');
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->uuid('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table
                ->foreign('lead_id')
                ->references('id')
                ->on('leads')
                ->cascadeOnDelete();
            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_status_history');
    }
};
