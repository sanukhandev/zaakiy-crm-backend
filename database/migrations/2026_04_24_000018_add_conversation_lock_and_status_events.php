<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (!Schema::hasColumn('leads', 'conversation_owner_id')) {
                    $table->uuid('conversation_owner_id')->nullable()->after('assigned_to');
                }
                if (!Schema::hasColumn('leads', 'conversation_owner_at')) {
                    $table->timestampTz('conversation_owner_at')->nullable()->after('conversation_owner_id');
                }
                if (!Schema::hasColumn('leads', 'conversation_lock_expires_at')) {
                    $table->timestampTz('conversation_lock_expires_at')->nullable()->after('conversation_owner_at');
                }
                if (!Schema::hasColumn('leads', 'last_message_at')) {
                    $table->timestampTz('last_message_at')->nullable()->after('last_activity_at');
                }
                if (!Schema::hasColumn('leads', 'last_message_direction')) {
                    $table->string('last_message_direction', 16)->nullable()->after('last_message_at');
                }
                if (!Schema::hasColumn('leads', 'unread_count')) {
                    $table->integer('unread_count')->default(0)->after('last_message_direction');
                }
            });

            if (!Schema::hasColumn('leads', 'last_message_at')) {
                return;
            }

            Schema::table('leads', function (Blueprint $table) {
                $table->index(['tenant_id', 'last_message_at']);
                $table->index(['tenant_id', 'conversation_owner_id', 'conversation_lock_expires_at'], 'leads_tenant_owner_lock_idx');
            });
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (!Schema::hasColumn('messages', 'status')) {
                    $table->string('status', 16)->default('sent')->after('direction');
                }
                if (!Schema::hasColumn('messages', 'metadata')) {
                    $table->json('metadata')->nullable()->after('status');
                }
            });

            Schema::table('messages', function (Blueprint $table) {
                $table->index(['tenant_id', 'lead_id', 'created_at'], 'messages_tenant_lead_created_idx');
                $table->index(['tenant_id', 'external_id'], 'messages_tenant_external_idx');
            });
        }

        if (!Schema::hasTable('message_status_events')) {
            Schema::create('message_status_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('message_id');
                $table->string('external_id', 255)->nullable();
                $table->string('status', 16);
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'message_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('message_status_events')) {
            Schema::drop('message_status_events');
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (Schema::hasColumn('messages', 'metadata')) {
                    $table->dropColumn('metadata');
                }
                if (Schema::hasColumn('messages', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }

        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (Schema::hasColumn('leads', 'conversation_lock_expires_at')) {
                    $table->dropColumn('conversation_lock_expires_at');
                }
                if (Schema::hasColumn('leads', 'conversation_owner_at')) {
                    $table->dropColumn('conversation_owner_at');
                }
                if (Schema::hasColumn('leads', 'conversation_owner_id')) {
                    $table->dropColumn('conversation_owner_id');
                }
            });
        }
    }
};
