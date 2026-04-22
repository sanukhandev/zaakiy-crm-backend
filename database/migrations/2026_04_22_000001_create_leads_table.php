<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('name', 255);
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('source', 50)->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->uuid('assigned_to')->nullable()->index();
            $table->jsonb('metadata')->nullable()->default('{}');
            $table->softDeletes();
            $table->timestamps();

            // Composite index for tenant + status listing (most common query)
            $table->index(['tenant_id', 'status']);
            // Composite index for tenant + assigned_to (agent inbox)
            $table->index(['tenant_id', 'assigned_to']);
            // Partial unique: one active lead per email per tenant
            // Cannot do conditional unique in Blueprint; handled at DB level in raw SQL below
        });

        // Unique constraint on active (non-deleted) email per tenant
        DB::statement(
            'CREATE UNIQUE INDEX leads_tenant_email_unique
             ON leads (tenant_id, email)
             WHERE email IS NOT NULL AND deleted_at IS NULL',
        );

        // Unique constraint on active (non-deleted) phone per tenant
        DB::statement(
            'CREATE UNIQUE INDEX leads_tenant_phone_unique
             ON leads (tenant_id, phone)
             WHERE phone IS NOT NULL AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
