<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

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
        return DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $auth['tenant_id'])
            ->update($payload);
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
