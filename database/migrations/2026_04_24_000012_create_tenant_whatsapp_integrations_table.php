<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_whatsapp_integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->unique();
            $table->string('business_account_id')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->string('sender_label')->nullable();
            $table->string('base_url')->nullable();
            $table->string('api_version', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_whatsapp_integrations');
    }
};
