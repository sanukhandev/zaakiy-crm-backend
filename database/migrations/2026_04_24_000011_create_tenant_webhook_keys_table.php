<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_webhook_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('provider', 32)->default('whatsapp');
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 16);
            $table->uuid('created_by')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
            $table->index(['provider', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_webhook_keys');
    }
};
