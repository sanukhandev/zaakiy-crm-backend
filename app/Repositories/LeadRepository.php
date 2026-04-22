<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class LeadRepository
{
    public function create($data)
    {
        return DB::table('leads')->insertGetId($data);
    }

    public function getPaginated($tenantId, $filters = [])
    {
        $query = DB::table('leads')->where('tenant_id', $tenantId);

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
        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function update($id, $auth, $payload)
    {
        // 1. Fetch existing lead
        $lead = DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $auth['tenant_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$lead) {
            throw new \Exception('Lead not found');
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

        // 4. Handle status change (with history)
        if (isset($payload['status']) && $payload['status'] !== $lead->status) {
            // Insert status history
            DB::table('lead_status_history')->insert([
                'lead_id' => $id,
                'old_status' => $lead->status,
                'new_status' => $payload['status'],
                'changed_by' => $auth['user_id'],
                'created_at' => now(),
            ]);

            $updateData['status'] = $payload['status'];
        }

        // 5. Add updated timestamp
        $updateData['updated_at'] = now();

        // 6. Perform update
        DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $auth['tenant_id'])
            ->update($updateData);

        // 7. Cache invalidation (important)
        Cache::flush(); // simple for now (later optimize with tags)

        // 8. Return updated lead
        return DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $auth['tenant_id'])
            ->first();
    }
    public function findDuplicate($tenantId, $data)
    {
        return DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($data) {
                if (!empty($data['email'])) {
                    $q->orWhere('email', $data['email']);
                }
                if (!empty($data['phone'])) {
                    $q->orWhere('phone', $data['phone']);
                }
            })
            ->first();
    }
    public function delete($id, $tenantId)
    {
        return DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update(['deleted_at' => now()]);
    }
}
