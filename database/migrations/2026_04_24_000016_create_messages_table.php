<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('lead_id');
            $table->string('channel', 32); // whatsapp, meta, etc.
            $table->string('direction', 16); // inbound, outbound
            $table->text('content');
            $table->string('external_id', 255)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id', 'created_at']);
            $table->index(['tenant_id', 'channel']);
            $table->unique(['external_id', 'tenant_id']);
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
