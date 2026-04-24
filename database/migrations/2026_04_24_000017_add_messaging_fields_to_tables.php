<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add status column to messages table
        if (Schema::hasTable('messages') && !Schema::hasColumn('messages', 'status')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->string('status', 16)->default('sent')->after('direction'); // sent, delivered, read, failed
            });
        }

        // Add conversation tracking to leads table
        if (Schema::hasTable('leads')) {
            if (!Schema::hasColumn('leads', 'last_message_at')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->timestamp('last_message_at')->nullable()->after('last_activity_at');
                });
            }
            if (!Schema::hasColumn('leads', 'last_message_direction')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->string('last_message_direction', 16)->nullable()->after('last_message_at');
                });
            }
            if (!Schema::hasColumn('leads', 'unread_count')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->integer('unread_count')->default(0)->after('last_message_direction');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'status')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasTable('leads')) {
            if (Schema::hasColumn('leads', 'last_message_at')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->dropColumn('last_message_at');
                });
            }
            if (Schema::hasColumn('leads', 'last_message_direction')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->dropColumn('last_message_direction');
                });
            }
            if (Schema::hasColumn('leads', 'unread_count')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->dropColumn('unread_count');
                });
            }
        }
    }
};
