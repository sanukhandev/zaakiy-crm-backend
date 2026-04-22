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

        // Filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%$search%")
                    ->orWhere('email', 'ilike', "%$search%")
                    ->orWhere('phone', 'ilike', "%$search%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 10);
    }

    public function update($id, $tenantId, $data)
    {
        return DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($data);
    }
}
