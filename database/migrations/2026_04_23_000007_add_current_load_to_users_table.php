<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // No-op: users are managed by Supabase auth/user management.
    }

    public function down(): void
    {
        // No-op.
    }
};
