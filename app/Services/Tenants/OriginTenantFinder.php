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
        $domain = null;

        // Priority 1: X-Company-Domain header (most reliable, sent by frontend)
        if ($request->header('X-Company-Domain')) {
            $domain = $request->header('X-Company-Domain');
        }
        // Priority 2: Origin header (for CORS requests from browser)
        elseif ($request->header('Origin')) {
            $domain = parse_url($request->header('Origin'), PHP_URL_HOST);
        }
        // Priority 3: Referer header (fallback)
        elseif ($request->header('Referer')) {
            $domain = parse_url($request->header('Referer'), PHP_URL_HOST);
        }

        // If no domain found, log and return null
        if (!$domain) {
            Log::warning('Could not determine company domain', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'x_company_domain' => $request->header('X-Company-Domain'),
                'origin' => $request->header('Origin'),
                'referer' => $request->header('Referer'),
                'host' => $request->header('Host'),
                'user_agent' => $request->header('User-Agent'),
            ]);
            return null;
        }

        // Validate domain format (prevent injection attacks)
        if (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
            Log::warning('Invalid domain format', ['domain' => $domain]);
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