<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        if (!Schema::hasColumn('leads', 'last_inbound_at')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->timestamp('last_inbound_at')->nullable()->after('updated_at');
            });
        }

        if (!Schema::hasColumn('leads', 'last_outbound_at')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->timestamp('last_outbound_at')->nullable()->after('last_inbound_at');
            });
        }

        if (!Schema::hasColumn('leads', 'last_activity_at')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->timestamp('last_activity_at')->nullable()->after('last_outbound_at');
            });
        }

        if (!Schema::hasColumn('leads', 'auto_replied_at')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->timestamp('auto_replied_at')->nullable()->after('last_activity_at');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            foreach (['auto_replied_at', 'last_activity_at', 'last_outbound_at', 'last_inbound_at'] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
