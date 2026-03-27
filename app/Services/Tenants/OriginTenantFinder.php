<?php

namespace App\Services\Tenants;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class OriginTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        // Single-tenant: build tenant from .env — no tenants table needed
        $tenant = new Tenant();
        $tenant->forceFill([
            'id'                => 1,
            'name'              => config('app.name'),
            'database'          => config('database.connections.mysql.database'),
            'database_username' => config('database.connections.mysql.username'),
            'is_active'         => true,
        ]);
        $tenant->exists = true;

        return $tenant;
    }
}