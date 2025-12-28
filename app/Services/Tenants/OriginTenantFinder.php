<?php

namespace App\Services\Tenants;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class OriginTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        // Get origin from header (sent by frontend)
        $origin = $request->header('Origin') ?: $request->header('Referer');

        if (!$origin) {
            Log::warning('No Origin header in request');
            return null;
        }

        // Extract domain from origin
        $domain = parse_url($origin, PHP_URL_HOST);

        if (!$domain) {
            Log::warning('Could not extract domain from origin', ['origin' => $origin]);
            return null;
        }

        // Find tenant by domain using landlord connection
        $tenantModelClass = config('multitenancy.tenant_model');
        $landlordConnection = config('multitenancy.landlord_database_connection_name', 'mysql');

        $tenant = $tenantModelClass::on($landlordConnection)
            ->where('domain', $domain)
            ->where('is_active', true)
            ->first();

        if ($tenant) {
            // \Log::info('Tenant identified', [
            //     'tenant' => $tenant->name,
            //     'domain' => $domain,
            //     'database' => $tenant->database,
            // ]);
        } else {
            Log::warning('No tenant found for domain', ['domain' => $domain]);
        }

        return $tenant;
    }
}