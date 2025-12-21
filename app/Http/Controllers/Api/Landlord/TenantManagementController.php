<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
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

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => $tenant,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified tenant
     */
    public function show(Tenant $tenant)
    {
        return response()->json([
            'success' => true,
            'data' => $tenant,
        ]);
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

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => $tenant->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant',
                'error' => $e->getMessage(),
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Tenant deactivated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activate a deactivated tenant
     */
    public function activate(Tenant $tenant)
    {
        try {
            $tenant->update(['is_active' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Tenant activated successfully',
                'data' => $tenant->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate tenant',
                'error' => $e->getMessage(),
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Migrations executed successfully',
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to run migrations',
                'error' => $e->getMessage(),
            ], 500);
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

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
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
