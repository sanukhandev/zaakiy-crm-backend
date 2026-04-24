<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Support\CacheHelper;
use App\Support\PhoneNumber;

class LeadRepository
{
    private function tenantVersionKey(string $tenantId): string
    {
        return 'leads_ver_' . $tenantId;
    }

    private function currentVersion(string $tenantId): int
    {
        if (!CacheHelper::isEnabled()) {
            return 1;
        }

        return (int) Cache::get($this->tenantVersionKey($tenantId), 1);
    }

    private function bumpVersion(string $tenantId): void
    {
        if (!CacheHelper::isEnabled()) {
            return;
        }

        $versionKey = $this->tenantVersionKey($tenantId);

        if (!Cache::has($versionKey)) {
            Cache::forever($versionKey, 1);
        }

        Cache::increment($versionKey);
    }

    private function paginatedCacheKey(string $tenantId, array $filters): string
    {
        $version = $this->currentVersion($tenantId);

        ksort($filters);

        return 'leads:list:' . $tenantId . ':v' . $version . ':' . md5(json_encode($filters));
    }

    private function pipelineCacheKey(string $tenantId): string
    {
        $version = $this->currentVersion($tenantId);

        return 'leads:pipeline:' . $tenantId . ':v' . $version;
    }

    private function bustCache(string $tenantId): void
    {
        if (!CacheHelper::isEnabled()) {
            return;
        }

        $this->bumpVersion($tenantId);

        $store = Cache::getStore();

        if (method_exists($store, 'tags')) {
            try {
                Cache::tags(['leads', $tenantId])->flush();
                return;
            } catch (\Throwable) {
                // Fallback to key-based invalidation for non-taggable stores.
            }
        }

    }

    public function create(array $data): string
    {
        $data['phone'] = PhoneNumber::normalize($data['phone'] ?? null);
        $data['id'] = $data['id'] ?? (string) Str::uuid();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('leads')->insert($data);

        Log::info('Lead created', [
            'lead_id' => $data['id'],
            'tenant_id' => $data['tenant_id'] ?? null,
            'status' => $data['status'] ?? null,
        ]);

        $this->bustCache($data['tenant_id']);

        return $data['id'];
    }

    public function getPaginated(string $tenantId, array $filters = [])
    {
        $cacheKey = $this->paginatedCacheKey($tenantId, $filters);
        $perPage = min((int) ($filters['per_page'] ?? 10), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);

        $cached = CacheHelper::remember($cacheKey, function () use ($tenantId, $filters, $perPage, $page) {
            $query = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at');

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['source'])) {
                $query->where('source', $filters['source']);
            }

            if (!empty($filters['assigned_to'])) {
                $query->where('assigned_to', $filters['assigned_to']);
            }

            if (!empty($filters['stage_id'])) {
                $query->where('stage_id', $filters['stage_id']);
            }

            if (!empty($filters['search'])) {
                $search = $filters['search'];

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%$search%")
                        ->orWhere('email', 'ilike', "%$search%")
                        ->orWhere('phone', 'ilike', "%$search%");
                });
            }

            $allowedSorts = ['created_at', 'name', 'status'];

            $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts)
                ? $filters['sort_by']
                : 'created_at';

            $sortOrder =
                ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $total = (clone $query)->count();
            $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->toArray();

            return ['total' => $total, 'items' => $items, 'per_page' => $perPage, 'page' => $page];
        });

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $cached['items'],
            $cached['total'],
            $cached['per_page'],
            $cached['page'],
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()],
        );
    }

    public function getPipeline(string $tenantId): array
    {
        $cacheKey = $this->pipelineCacheKey($tenantId);

        return CacheHelper::remember($cacheKey, function () use ($tenantId) {
            $stages = ['new', 'contacted', 'qualified', 'won', 'lost'];
            $grouped = array_fill_keys($stages, []);

            $leads = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->orderBy('status')
                ->orderBy('position')
                ->get();

            foreach ($leads as $lead) {
                if (array_key_exists($lead->status, $grouped)) {
                    $grouped[$lead->status][] = $lead;
                }
            }

            return $grouped;
        });
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
            $updateData['phone'] = PhoneNumber::normalize($payload['phone']);
        }

        if (isset($payload['email'])) {
            $updateData['email'] = $payload['email'];
        }

        if (isset($payload['source'])) {
            $updateData['source'] = $payload['source'];
        }

        if (array_key_exists('assigned_to', $payload)) {
            $updateData['assigned_to'] = $payload['assigned_to'];
        }

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

    public function moveLead(string $id, array $auth, array $payload): object
    {
        DB::transaction(function () use ($id, $auth, $payload) {
            $lead = DB::table('leads')
                ->where('id', $id)
                ->where('tenant_id', $auth['tenant_id'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$lead) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                    'Lead not found',
                );
            }

            $oldStatus = $lead->status;
            $newStatus = $payload['status'];
            $oldPosition = (int) ($lead->position ?? 0);

            $targetCount = DB::table('leads')
                ->where('tenant_id', $auth['tenant_id'])
                ->where('status', $newStatus)
                ->whereNull('deleted_at')
                ->when($newStatus === $oldStatus, function ($q) use ($id) {
                    $q->where('id', '!=', $id);
                })
                ->count();

            $targetPosition = max(0, (int) $payload['position']);
            $targetPosition = min($targetPosition, (int) $targetCount);

            if ($newStatus === $oldStatus) {
                if ($targetPosition > $oldPosition) {
                    DB::table('leads')
                        ->where('tenant_id', $auth['tenant_id'])
                        ->where('status', $oldStatus)
                        ->whereNull('deleted_at')
                        ->where('id', '!=', $id)
                        ->whereBetween('position', [$oldPosition + 1, $targetPosition])
                        ->update([
                            'position' => DB::raw('position - 1'),
                            'updated_at' => now(),
                        ]);
                } elseif ($targetPosition < $oldPosition) {
                    DB::table('leads')
                        ->where('tenant_id', $auth['tenant_id'])
                        ->where('status', $oldStatus)
                        ->whereNull('deleted_at')
                        ->where('id', '!=', $id)
                        ->whereBetween('position', [$targetPosition, $oldPosition - 1])
                        ->update([
                            'position' => DB::raw('position + 1'),
                            'updated_at' => now(),
                        ]);
                }

                DB::table('leads')
                    ->where('id', $id)
                    ->where('tenant_id', $auth['tenant_id'])
                    ->whereNull('deleted_at')
                    ->update([
                        'position' => $targetPosition,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('leads')
                    ->where('tenant_id', $auth['tenant_id'])
                    ->where('status', $oldStatus)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $id)
                    ->where('position', '>', $oldPosition)
                    ->update([
                        'position' => DB::raw('position - 1'),
                        'updated_at' => now(),
                    ]);

                DB::table('leads')
                    ->where('tenant_id', $auth['tenant_id'])
                    ->where('status', $newStatus)
                    ->whereNull('deleted_at')
                    ->where('position', '>=', $targetPosition)
                    ->update([
                        'position' => DB::raw('position + 1'),
                        'updated_at' => now(),
                    ]);

                DB::table('lead_status_history')->insert([
                    'id' => (string) Str::uuid(),
                    'lead_id' => $id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'changed_by' => $auth['user_id'],
                    'created_at' => now(),
                ]);

                DB::table('leads')
                    ->where('id', $id)
                    ->where('tenant_id', $auth['tenant_id'])
                    ->whereNull('deleted_at')
                    ->update([
                        'status' => $newStatus,
                        'position' => $targetPosition,
                        'updated_at' => now(),
                    ]);
            }

            Log::info('Lead moved in pipeline', [
                'lead_id' => $id,
                'tenant_id' => $auth['tenant_id'],
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'to_position' => $targetPosition,
                'moved_by' => $auth['user_id'] ?? null,
            ]);
        });

        $this->bustCache($auth['tenant_id']);

        return DB::table('leads')
            ->select(['id', 'status', 'position'])
            ->where('id', $id)
            ->where('tenant_id', $auth['tenant_id'])
            ->whereNull('deleted_at')
            ->first();
    }
    public function findDuplicate(string $tenantId, array $data): ?object
    {
        $normalizedPhone = PhoneNumber::normalize($data['phone'] ?? null);
        $hasEmail = !empty($data['email']);
        $hasPhone = !empty($normalizedPhone);

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
                    $q->orWhere('phone', PhoneNumber::normalize($data['phone'] ?? null));
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

        Log::info('Lead deleted', [
            'lead_id' => $id,
            'tenant_id' => $tenantId,
        ]);

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

    public function findByIdForTenant(string $id, string $tenantId): ?object
    {
        return DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function findByPhone(string $tenantId, string $phone): ?object
    {
        $normalizedPhone = PhoneNumber::normalize($phone);
        if (!$normalizedPhone) {
            return null;
        }

        return DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('phone', $normalizedPhone)
            ->whereNull('deleted_at')
            ->first();
    }

    private function decodeMetadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function createOrUpdateFromWebhook(
        string $tenantId,
        array $payload,
        ?string $assignedTo,
        ?string $stageId,
    ): array {
        return DB::transaction(function () use ($tenantId, $payload, $assignedTo, $stageId) {
            $payload['phone'] = PhoneNumber::normalize($payload['phone'] ?? null);
            $duplicate = $this->findDuplicate($tenantId, $payload);

            $incomingMetadata = is_array($payload['metadata'] ?? null)
                ? $payload['metadata']
                : [];

            if ($duplicate) {
                $existingMetadata = $this->decodeMetadata($duplicate->metadata ?? null);

                DB::table('leads')
                    ->where('id', $duplicate->id)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'name' => $payload['name'] ?? $duplicate->name,
                        'phone' => $payload['phone'] ?? $duplicate->phone,
                        'email' => $payload['email'] ?? $duplicate->email,
                        'source' => $payload['source'] ?? $duplicate->source,
                        'metadata' => json_encode(array_merge($existingMetadata, $incomingMetadata)),
                        'updated_at' => now(),
                    ]);

                $this->bustCache($tenantId);

                return [
                    'id' => $duplicate->id,
                    'action' => 'updated',
                ];
            }

            $id = $this->create([
                'tenant_id' => $tenantId,
                'name' => $payload['name'] ?? ($payload['phone'] ?? 'Webhook Lead'),
                'phone' => $payload['phone'] ?? null,
                'email' => $payload['email'] ?? null,
                'source' => $payload['source'] ?? 'webhook',
                'status' => $payload['status'] ?? 'new',
                'stage_id' => $stageId,
                'assigned_to' => $assignedTo,
                'metadata' => json_encode($incomingMetadata),
            ]);

            return [
                'id' => $id,
                'action' => 'created',
            ];
        });
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
            'created_by' => $auth['user_id'] ?? null,
            'created_at' => now(),
        ]);

        if (Schema::hasColumn('leads', 'last_activity_at')) {
            DB::table('leads')
                ->where('id', $leadId)
                ->where('tenant_id', $auth['tenant_id'])
                ->update([
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return $id;
    }

    public function listActivities(
    string $leadId,
    string $tenantId,
    int $perPage = 20,
) {
    // Ensure lead belongs to tenant
    $this->ensureLeadExistsForTenant($leadId, $tenantId);

    $query = DB::table('lead_activities')
        ->where('lead_id', $leadId);

    // ✅ Apply tenant filter ONLY if column exists
    if (Schema::hasColumn('lead_activities', 'tenant_id')) {
        $query->where('tenant_id', $tenantId);
    }

    return $query
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

        Log::info('Leads bulk updated', [
            'tenant_id' => $auth['tenant_id'],
            'count' => $affected,
            'lead_ids' => $leadIds,
            'updated_by' => $auth['user_id'] ?? null,
        ]);

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

        Log::info('Leads bulk assigned', [
            'tenant_id' => $auth['tenant_id'],
            'count' => $affected,
            'lead_ids' => $leadIds,
            'assigned_to' => $assignedTo,
            'updated_by' => $auth['user_id'] ?? null,
        ]);

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

        Log::info('Leads bulk deleted', [
            'tenant_id' => $auth['tenant_id'],
            'count' => $affected,
            'lead_ids' => $leadIds,
            'deleted_by' => $auth['user_id'] ?? null,
        ]);

        return $affected;
    }

    public function getAssignedUsersForLeadIds(string $tenantId, array $leadIds): array
    {
        return DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $leadIds)
            ->whereNull('deleted_at')
            ->pluck('assigned_to')
            ->all();
    }

    public function updateAutomationState(string $leadId, string $tenantId, array $attributes): void
    {
        if ($attributes === []) {
            return;
        }

        $updates = [];

        foreach (['last_inbound_at', 'last_outbound_at', 'last_activity_at', 'auto_replied_at'] as $field) {
            if (array_key_exists($field, $attributes) && Schema::hasColumn('leads', $field)) {
                $updates[$field] = $attributes[$field];
            }
        }

        if ($updates === []) {
            return;
        }

        $updates['updated_at'] = now();

        DB::table('leads')
            ->where('id', $leadId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->update($updates);

        $this->bustCache($tenantId);
    }

    public function findByPhoneOrEmailAndTenant(string $tenantId, ?string $phone, ?string $email): ?object
    {
        $query = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($phone && $email) {
            $query->where(function ($q) use ($phone, $email) {
                $q->orWhere('phone', PhoneNumber::normalize($phone))
                  ->orWhere('email', $email);
            });
        } elseif ($phone) {
            $query->where('phone', PhoneNumber::normalize($phone));
        } elseif ($email) {
            $query->where('email', $email);
        } else {
            return null;
        }

        return $query->first();
    }

    public function findById(string $tenantId, string $id): ?object
    {
        return DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
    }

    public function updateActivityTimestamps(string $tenantId, string $leadId, array $timestamps): void
    {
        $allowed = ['last_inbound_at', 'last_outbound_at', 'last_activity_at'];
        $updates = array_intersect_key($timestamps, array_flip($allowed));

        if (!$updates) {
            return;
        }

        DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('id', $leadId)
            ->whereNull('deleted_at')
            ->update($updates);

        $this->bustCache($tenantId);
    }
}
