<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class LeadRepository
{
    public function create($data)
    {
        return DB::table('leads')->insertGetId($data);
    }

    public function getAll($tenantId)
    {
        return DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function update($id, $tenantId, $data)
    {
        return DB::table('leads')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($data);
    }
}