<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'position')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->integer('position')->default(0)->after('status');
                $table->index(['tenant_id', 'status', 'position']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'position')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'status', 'position']);
                $table->dropColumn('position');
            });
        }
    }
};
