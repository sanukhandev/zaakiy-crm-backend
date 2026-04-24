<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchemaCompatibilityChecker
{
    private const CACHE_KEY = 'schema_compat_result';
    private const CACHE_TTL = 3600; // 1 hour

    public function check(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->runCheck());
        } catch (\Throwable) {
            // Cache unavailable — run uncached rather than blocking boot
            return $this->runCheck();
        }
    }

    private function runCheck(): array
    {
        $errors = [];
        $warnings = [];

        $requiredTables = [
            'leads' => [
                'id',
                'tenant_id',
                'name',
                'phone',
                'email',
                'source',
                'status',
                'score',
                'position',
                'assigned_to',
                'metadata',
                'deleted_at',
                'created_at',
                'updated_at',
            ],
            'lead_status_history' => [
                'id',
                'lead_id',
                'old_status',
                'new_status',
                'changed_by',
                'created_at',
            ],
            'lead_activity_logs' => [
                'id',
                'lead_id',
                'tenant_id',
                'type',
                'content',
                'created_by',
                'created_at',
            ],
            'tenants' => ['id'],
            'pipeline_stages' => ['id', 'tenant_id', 'name', 'order_index'],
            'messages' => [
                'id',
                'tenant_id',
                'lead_id',
                'channel',
                'sender',
                'content',
                'direction',
                'external_id',
                'created_at',
            ],
        ];

        foreach ($requiredTables as $table => $columns) {
            if (!Schema::hasTable($table)) {
                $errors[] = "Missing table: {$table}";

                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $errors[] = "Missing column: {$table}.{$column}";
                }
            }
        }

        $this->checkIndex('leads', 'leads_tenant_email_unique', $warnings);
        $this->checkIndex('leads', 'leads_tenant_phone_unique', $warnings);

        if (
            Schema::hasTable('leads') &&
            Schema::hasColumn('leads', 'position')
        ) {
            $this->checkIndex(
                'leads',
                'leads_tenant_id_status_position_index',
                $warnings,
            );
        }

        $this->checkForeignKey(
            'lead_status_history',
            'lead_status_history_lead_id_foreign',
            $warnings,
        );

        $this->checkForeignKey(
            'lead_activity_logs',
            'lead_activity_logs_lead_id_foreign',
            $warnings,
        );

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function checkIndex(
        string $table,
        string $indexName,
        array &$warnings,
    ): void {
        if (!Schema::hasTable($table)) {
            return;
        }

        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                $exists = DB::table('pg_indexes')
                    ->where('tablename', $table)
                    ->where('indexname', $indexName)
                    ->exists();

                if (!$exists) {
                    $warnings[] = "Missing index: {$indexName}";
                }

                return;
            }

            if ($driver === 'mysql') {
                $dbName = DB::connection()->getDatabaseName();

                $exists = DB::table('information_schema.statistics')
                    ->where('table_schema', $dbName)
                    ->where('table_name', $table)
                    ->where('index_name', $indexName)
                    ->exists();

                if (!$exists) {
                    $warnings[] = "Missing index: {$indexName}";
                }

                return;
            }

            if ($driver === 'sqlite') {
                $rows = DB::select("PRAGMA index_list('{$table}')");
                $exists = collect($rows)->contains(function ($row) use (
                    $indexName,
                ) {
                    return ($row->name ?? null) === $indexName;
                });

                if (!$exists) {
                    $warnings[] = "Missing index: {$indexName}";
                }

                return;
            }

            $warnings[] = "Index check skipped for driver: {$driver}";
        } catch (\Throwable $e) {
            $warnings[] = "Index check failed for {$indexName}: {$e->getMessage()}";
        }
    }

    private function checkForeignKey(
        string $table,
        string $constraintName,
        array &$warnings,
    ): void {
        if (!Schema::hasTable($table)) {
            return;
        }

        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                $exists = DB::table('information_schema.table_constraints')
                    ->where('table_name', $table)
                    ->where('constraint_type', 'FOREIGN KEY')
                    ->where('constraint_name', $constraintName)
                    ->exists();

                if (!$exists) {
                    $warnings[] = "Missing foreign key: {$constraintName}";
                }

                return;
            }

            if ($driver === 'mysql') {
                $dbName = DB::connection()->getDatabaseName();

                $exists = DB::table('information_schema.table_constraints')
                    ->where('table_schema', $dbName)
                    ->where('table_name', $table)
                    ->where('constraint_type', 'FOREIGN KEY')
                    ->where('constraint_name', $constraintName)
                    ->exists();

                if (!$exists) {
                    $warnings[] = "Missing foreign key: {$constraintName}";
                }

                return;
            }

            $warnings[] = "Foreign key check skipped for driver: {$driver}";
        } catch (\Throwable $e) {
            $warnings[] = "Foreign key check failed for {$constraintName}: {$e->getMessage()}";
        }
    }
}
