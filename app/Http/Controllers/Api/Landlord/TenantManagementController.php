<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Traits\HasPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantManagementController extends Controller
{
    use HasPagination;

    /**
     * List all tenants.
     */
    public function index(Request $request)
    {
        $query = Tenant::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('tenant_key', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderBy('created_at', 'desc')->paginate(15);

        return ApiResponse::paginated('Tenants retrieved successfully', $tenants);
    }

    /**
     * Create a new tenant record (and optionally its database + migrations).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'tenant_key'        => 'required|string|max:255|unique:mysql.tenants,tenant_key',
            'domain'            => 'required|string|max:255|unique:mysql.tenants,domain',
            'database'          => 'required|string|max:255',
            'database_username' => 'nullable|string|max:255',
            'database_password' => 'nullable|string|max:255',
            'settings'          => 'nullable|array',
            'is_active'         => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            $tenant = Tenant::create([
                'name'              => $validated['name'],
                'tenant_key'        => $validated['tenant_key'],
                'domain'            => $validated['domain'],
                'database'          => $validated['database'],
                'database_username' => $validated['database_username'] ?? null,
                'database_password' => $validated['database_password'] ?? null,
                'settings'          => $validated['settings'] ?? [],
                'is_active'         => $validated['is_active'] ?? true,
            ]);

            if ($request->boolean('create_database')) {
                $this->createTenantDatabase($validated['database']);
            }

            if ($request->boolean('run_migrations')) {
                \Illuminate\Support\Facades\Artisan::call('tenants:artisan', [
                    'artisanCommand' => 'migrate --force',
                    '--tenant'       => $tenant->id,
                ]);
            }

            DB::commit();

            return ApiResponse::store('Tenant created successfully', $tenant);

        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::serverError('Failed to create tenant: ' . $e->getMessage());
        }
    }

    /**
     * Show a specific tenant.
     */
    public function show(Tenant $tenant)
    {
        return ApiResponse::show('Tenant retrieved successfully', $tenant);
    }

    /**
     * Update tenant record details.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name'              => 'sometimes|required|string|max:255',
            'tenant_key'        => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('mysql.tenants', 'tenant_key')->ignore($tenant->id),
            ],
            'domain'            => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('mysql.tenants', 'domain')->ignore($tenant->id),
            ],
            'database'          => 'sometimes|required|string|max:255',
            'database_username' => 'nullable|string|max:255',
            'database_password' => 'nullable|string|max:255',
            'settings'          => 'nullable|array',
            'is_active'         => 'boolean',
        ]);

        try {
            $tenant->update($validated);

            return ApiResponse::update('Tenant updated successfully', $tenant->fresh());

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to update tenant: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate a tenant.
     */
    public function destroy(Tenant $tenant)
    {
        try {
            $tenant->update(['is_active' => false]);

            return ApiResponse::update('Tenant deactivated successfully');

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to deactivate tenant: ' . $e->getMessage());
        }
    }

    /**
     * Activate a deactivated tenant.
     */
    public function activate(Tenant $tenant)
    {
        try {
            $tenant->update(['is_active' => true]);

            return ApiResponse::update('Tenant activated successfully', $tenant->fresh());

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to activate tenant: ' . $e->getMessage());
        }
    }

    /**
     * Tenant statistics.
     */
    public function stats()
    {
        return ApiResponse::send('Tenant statistics retrieved successfully', 200, [
            'total_tenants'    => Tenant::count(),
            'active_tenants'   => Tenant::where('is_active', true)->count(),
            'inactive_tenants' => Tenant::where('is_active', false)->count(),
            'recent_tenants'   => Tenant::orderBy('created_at', 'desc')
                ->take(5)
                ->get(['id', 'name', 'domain', 'created_at']),
        ]);
    }

    private function createTenantDatabase(string $databaseName): void
    {
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
}
