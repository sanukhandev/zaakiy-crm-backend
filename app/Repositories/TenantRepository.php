<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TenantRepository
{
    public function findById($tenantId)
    {
        return DB::table('tenants')->where('id', $tenantId)->first();
    }
}
