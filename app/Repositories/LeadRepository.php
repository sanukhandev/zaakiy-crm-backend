<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LeadRepository
{
    private function cacheKey(string $tenantId): string
    {
        return 'leads_' . $tenantId;
    }

    private function bustCache(string $tenantId): void
    {
        Cache::forget($this->cacheKey($tenantId));
    }

    public function create(array $data): string
    {
        $data['id'] = $data['id'] ?? (string) Str::uuid();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('leads')->insert($data);

        $this->bustCache($data['tenant_id']);

        return $data['id'];
    }

    public function getPaginated(string $tenantId, array $filters = [])
    {
        $perPage = min((int) ($filters['per_page'] ?? 10), 100);

        $query = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        // Filter: status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter: source
        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%$search%")
                    ->orWhere('email', 'ilike', "%$search%")
                    ->orWhere('phone', 'ilike', "%$search%");
            });
        }

        // Sorting
        $allowedSorts = ['created_at', 'name', 'status'];

        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts)
            ? $filters['sort_by']
            : 'created_at';

        $sortOrder =
            ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        return $query->paginate($perPage);
    }

    public function update(string $id, array $auth, array $payload): object
    {
        // 1. Fetch existing lead (excludes soft-deleted)
        $lead = DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $auth['tenant_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$lead) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'Lead not found',
            );
        }

        // 2. Prepare update data (whitelist fields)
        $updateData = [];

        if (isset($payload['name'])) {
            $updateData['name'] = $payload['name'];
        }

        if (isset($payload['phone'])) {
            $updateData['phone'] = $payload['phone'];
        }

        if (isset($payload['email'])) {
            $updateData['email'] = $payload['email'];
        }

        if (isset($payload['source'])) {
            $updateData['source'] = $payload['source'];
        }

        $updateData['assigned_to'] =
            $payload['assigned_to'] ?? $auth['user_id'];

        // 3. Handle metadata safely
        if (isset($payload['metadata'])) {
            $updateData['metadata'] = is_array($payload['metadata'])
                ? json_encode($payload['metadata'])
                : $payload['metadata']; // already JSON string
        }

        // 4. Stage status change (history written inside transaction below)
        if (isset($payload['status']) && $payload['status'] !== $lead->status) {
            $updateData['status'] = $payload['status'];
        }

        // 5. Add updated timestamp
        $updateData['updated_at'] = now();

        // 6. Perform update + status history atomically
        DB::transaction(function () use (
            $id,
            $auth,
            $payload,
            $lead,
            $updateData,
        ) {
            if (
                isset($payload['status']) &&
                $payload['status'] !== $lead->status
            ) {
                DB::table('lead_status_history')->insert([
                    'id' => (string) Str::uuid(),
                    'lead_id' => $id,
                    'old_status' => $lead->status,
                    'new_status' => $payload['status'],
                    'changed_by' => $auth['user_id'],
                    'created_at' => now(),
                ]);
            }

            DB::table('leads')
                ->where('id', $id)
                ->where('tenant_id', $auth['tenant_id'])
                ->update($updateData);
        });

        // 7. Targeted cache invalidation — do NOT flush entire cache
        $this->bustCache($auth['tenant_id']);

        // 8. Return updated lead
        return DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $auth['tenant_id'])
            ->first();
    }
    public function findDuplicate(string $tenantId, array $data): ?object
    {
        $hasEmail = !empty($data['email']);
        $hasPhone = !empty($data['phone']);

        if (!$hasEmail && !$hasPhone) {
            return null;
        }

        return DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($data, $hasEmail, $hasPhone) {
                if ($hasEmail) {
                    $q->orWhere('email', $data['email']);
                }
                if ($hasPhone) {
                    $q->orWhere('phone', $data['phone']);
                }
            })
            ->first();
    }
    public function delete(string $id, string $tenantId): bool
    {
        $affected = DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        if (!$affected) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'Lead not found',
            );
        }

        $this->bustCache($tenantId);

        return true;
    }

    public function ensureLeadExistsForTenant(
        string $id,
        string $tenantId,
    ): object {
        $lead = DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        if (!$lead) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'Lead not found',
            );
        }

        return $lead;
    }

    public function addActivity(
        string $leadId,
        array $auth,
        array $payload,
    ): string {
        $this->ensureLeadExistsForTenant($leadId, $auth['tenant_id']);

        $id = (string) Str::uuid();

        DB::table('lead_activities')->insert([
            'id' => $id,
            'lead_id' => $leadId,
            'tenant_id' => $auth['tenant_id'],
            'type' => $payload['type'],
            'content' => $payload['content'],
            'created_by' => $auth['user_id'],
            'created_at' => now(),
        ]);

        return $id;
    }

    public function listActivities(
        string $leadId,
        string $tenantId,
        int $perPage = 20,
    ) {
        $this->ensureLeadExistsForTenant($leadId, $tenantId);

        return DB::table('lead_activities')
            ->where('lead_id', $leadId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate(min($perPage, 100));
    }

    public function bulkUpdate(array $auth, array $leadIds, array $payload): int
    {
        $affected = 0;

        DB::transaction(function () use (
            &$affected,
            $auth,
            $leadIds,
            $payload,
        ) {
            $updateData = [];

            foreach (['status', 'source', 'assigned_to'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $updateData[$field] = $payload[$field];
                }
            }

            if (array_key_exists('metadata', $payload)) {
                $updateData['metadata'] = json_encode($payload['metadata']);
            }

            $updateData['updated_at'] = now();

            if (
                isset($payload['status']) &&
                in_array($payload['status'], [
                    'new',
                    'contacted',
                    'qualified',
                    'won',
                    'lost',
                ])
            ) {
                $existing = DB::table('leads')
                    ->where('tenant_id', $auth['tenant_id'])
                    ->whereIn('id', $leadIds)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', $payload['status'])
                    ->select(['id', 'status'])
                    ->get();

                $historyRows = $existing
                    ->map(function ($row) use ($auth, $payload) {
                        return [
                            'id' => (string) Str::uuid(),
                            'lead_id' => $row->id,
                            'old_status' => $row->status,
                            'new_status' => $payload['status'],
                            'changed_by' => $auth['user_id'],
                            'created_at' => now(),
                        ];
                    })
                    ->all();

                if (!empty($historyRows)) {
                    DB::table('lead_status_history')->insert($historyRows);
                }
            }

            $affected = DB::table('leads')
                ->where('tenant_id', $auth['tenant_id'])
                ->whereIn('id', $leadIds)
                ->whereNull('deleted_at')
                ->update($updateData);
        });

        $this->bustCache($auth['tenant_id']);

        return $affected;
    }

    public function bulkAssign(
        array $auth,
        array $leadIds,
        string $assignedTo,
    ): int {
        $affected = DB::table('leads')
            ->where('tenant_id', $auth['tenant_id'])
            ->whereIn('id', $leadIds)
            ->whereNull('deleted_at')
            ->update([
                'assigned_to' => $assignedTo,
                'updated_at' => now(),
            ]);

        $this->bustCache($auth['tenant_id']);

        return $affected;
    }

    public function bulkDelete(array $auth, array $leadIds): int
    {
        $affected = DB::table('leads')
            ->where('tenant_id', $auth['tenant_id'])
            ->whereIn('id', $leadIds)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        $this->bustCache($auth['tenant_id']);

        return $affected;
    }
}
