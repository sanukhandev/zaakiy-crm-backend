<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'retry_count')) {
                $table->unsignedSmallInteger('retry_count')->default(0)->after('status');
            }

            if (!Schema::hasColumn('messages', 'last_attempt_at')) {
                $table->timestamp('last_attempt_at')->nullable()->after('retry_count');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'retry_count')) {
                $table->dropColumn('retry_count');
            }

            if (Schema::hasColumn('messages', 'last_attempt_at')) {
                $table->dropColumn('last_attempt_at');
            }
        });
    }
};
