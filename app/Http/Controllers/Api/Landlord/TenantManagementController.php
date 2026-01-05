<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class TenantManagementController extends Controller
{
    /**
     * Display a listing of all tenants
     */
    public function index(Request $request)
    {
        $query = Tenant::query();

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name, tenant_key or domain
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
     * Store a newly created tenant
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tenant_key' => 'required|string|max:255|unique:tenants,tenant_key',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'database' => 'required|string|max:255',
            'database_username' => 'nullable|string|max:255',
            'database_password' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            // Create tenant record
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'tenant_key' => $validated['tenant_key'],
                'domain' => $validated['domain'],
                'database' => $validated['database'],
                'database_username' => $validated['database_username'] ?? null,
                'database_password' => $validated['database_password'] ?? null,
                'settings' => $validated['settings'] ?? [],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Optional: Create tenant database if requested
            if ($request->boolean('create_database')) {
                $this->createTenantDatabase($validated['database']);
            }

            // Optional: Run migrations if requested
            if ($request->boolean('run_migrations')) {
                Artisan::call('tenants:artisan', [
                    'artisanCommand' => 'migrate --force',
                    '--tenant' => $tenant->id,
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
     * Display the specified tenant
     */
    public function show(Tenant $tenant)
    {
        return ApiResponse::show('Tenant retrieved successfully', $tenant);
    }

    /**
     * Update the specified tenant
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'tenant_key' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tenants', 'tenant_key')->ignore($tenant->id),
            ],
            'domain' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tenants', 'domain')->ignore($tenant->id),
            ],
            'database' => 'sometimes|required|string|max:255',
            'database_username' => 'nullable|string|max:255',
            'database_password' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        try {
            $tenant->update($validated);

            return ApiResponse::update('Tenant updated successfully', $tenant->fresh());

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to update tenant: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified tenant (soft delete)
     */
    public function destroy(Tenant $tenant)
    {
        try {
            // Deactivate instead of deleting
            $tenant->update(['is_active' => false]);

            return ApiResponse::update('Tenant deactivated successfully');

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to deactivate tenant: ' . $e->getMessage());
        }
    }

    /**
     * Activate a deactivated tenant
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
     * Run migrations for a specific tenant
     */
    public function runMigrations(Tenant $tenant)
    {
        try {
            Artisan::call('tenants:artisan', [
                'artisanCommand' => 'migrate --force',
                '--tenant' => $tenant->id,
            ]);

            $output = Artisan::output();

            return ApiResponse::send('Migrations executed successfully', 200, [
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to run migrations: ' . $e->getMessage());
        }
    }

    /**
     * Get tenant statistics
     */
    public function stats()
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'inactive_tenants' => Tenant::where('is_active', false)->count(),
            'recent_tenants' => Tenant::orderBy('created_at', 'desc')->take(5)->get(['id', 'name', 'domain', 'created_at']),
        ];

        return ApiResponse::send('Tenant statistics retrieved successfully', 200, $stats);
    }

    /**
     * Create tenant database
     */
    private function createTenantDatabase(string $databaseName)
    {
        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to create database: " . $e->getMessage());
        }
    }
}
