<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('messages') || Schema::hasColumn('messages', 'sender')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->string('sender', 255)->nullable()->after('channel');
            $table->index(['tenant_id', 'sender']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('messages') || !Schema::hasColumn('messages', 'sender')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'sender']);
            $table->dropColumn('sender');
        });
    }
};