<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Resources\Api\Setups\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\HasPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TenantManagementController extends Controller
{
    use HasPagination;
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
            'tenant_key' => 'required|string|max:255|unique:mysql.tenants,tenant_key',
            'domain' => 'required|string|max:255|unique:mysql.tenants,domain',
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
                Rule::unique('mysql.tenants', 'tenant_key')->ignore($tenant->id),
            ],
            'domain' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('mysql.tenants', 'domain')->ignore($tenant->id),
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
     * Run migrations for the landlord database
     */
    public function runLandlordMigrations()
    {
        try {
            Log::info('Starting landlord migrations', [
                'database' => 'mysql',
                'path' => 'database/migrations/landlord',
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp' => now()->toDateTimeString(),
            ]);

            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => 'database/migrations/landlord',
                '--force' => true,
            ]);

            $output = Artisan::output();
            $formattedOutput = $this->formatMigrationOutput($output);

            Log::info('Landlord migrations completed successfully', [
                'total_migrations' => $formattedOutput['total_migrations'],
                'migrations' => $formattedOutput['migrations'],
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return ApiResponse::send('Landlord migrations executed successfully', 200, $formattedOutput);

        } catch (\Exception $e) {
            Log::error('Landlord migrations failed', [
                'error' => $e->getMessage(),
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return ApiResponse::serverError('Failed to run landlord migrations: ' . $e->getMessage());
        }
    }

    /**
     * Run migrations for all tenants
     */
    public function runAllTenantsMigrations()
    {
        try {
            $tenants = Tenant::where('is_active', true)->get();

            Log::info('Starting migrations for all tenants', [
                'total_tenants' => count($tenants),
                'tenant_ids' => $tenants->pluck('id')->toArray(),
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp' => now()->toDateTimeString(),
            ]);

            $results = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($tenants as $tenant) {
                try {
                    Artisan::call('tenants:artisan', [
                        'artisanCommand' => 'migrate --force',
                        '--tenant' => $tenant->id,
                    ]);

                    $output = Artisan::output();
                    $formattedOutput = $this->formatMigrationOutput($output);

                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'status' => 'success',
                        'migrations' => $formattedOutput,
                    ];

                    Log::info('Tenant migrations completed', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'total_migrations' => $formattedOutput['total_migrations'],
                        'migrations' => $formattedOutput['migrations'],
                    ]);

                    $successCount++;
                } catch (\Exception $e) {
                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Tenant migrations failed', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'error' => $e->getMessage(),
                    ]);

                    $failedCount++;
                }
            }

            Log::info('All tenants migrations process completed', [
                'total_tenants' => count($tenants),
                'successful' => $successCount,
                'failed' => $failedCount,
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return ApiResponse::send('Migrations executed for all tenants', 200, [
                'total_tenants' => count($tenants),
                'successful' => $successCount,
                'failed' => $failedCount,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to run migrations for all tenants', [
                'error' => $e->getMessage(),
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return ApiResponse::serverError('Failed to run migrations for all tenants: ' . $e->getMessage());
        }
    }

    /**
     * Run migrations for a specific tenant
     */
    public function runMigrations(Tenant $tenant)
    {
        try {
            Log::info('Starting migrations for specific tenant', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_domain' => $tenant->domain,
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp' => now()->toDateTimeString(),
            ]);

            Artisan::call('tenants:artisan', [
                'artisanCommand' => 'migrate --force',
                '--tenant' => $tenant->id,
            ]);

            $output = Artisan::output();
            $formattedOutput = $this->formatMigrationOutput($output);

            Log::info('Tenant migrations completed successfully', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'total_migrations' => $formattedOutput['total_migrations'],
                'migrations' => $formattedOutput['migrations'],
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return ApiResponse::send('Migrations executed successfully', 200, $formattedOutput);

        } catch (\Exception $e) {
            Log::error('Tenant migrations failed', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'error' => $e->getMessage(),
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp' => now()->toDateTimeString(),
            ]);

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
     * Get all users for a specific tenant
     */
    public function getUsers(Request $request, Tenant $tenant)
    {
        try {
            $users = $tenant->execute(function () use ($request) {
                $query = User::query();

                // Apply filters
                if ($request->has('is_active')) {
                    $query->where('is_active', $request->boolean('is_active'));
                }

                if ($request->has('role')) {
                    $query->where('role', $request->role);
                }

                if ($request->has('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                }

                return $this->applyPagination($query, $request);
            });

            return ApiResponse::paginated(
                'Users retrieved successfully',
                $users,
                UserResource::class
            );

        } catch (\Exception $e) {
            Log::error('Failed to fetch users for tenant', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'error' => $e->getMessage(),
                'fetched_by' => auth()->user()?->email ?? 'System',
            ]);

            return ApiResponse::serverError('Failed to fetch users: ' . $e->getMessage());
        }
    }

    /**
     * Create or update a user for a specific tenant
     */
    public function createUser(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|min:6',
            'role' => ['required', 'string', Rule::in(User::getRoles())],
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $result = $tenant->execute(function () use ($validated) {
                // Check if user exists
                $user = User::where('email', $validated['email'])->first();

                if ($user) {
                    // User exists - update
                    $updateData = [
                        'name' => $validated['name'],
                        'role' => $validated['role'],
                        'updated_by' => 1,
                    ];

                    // Update is_active if provided
                    if (isset($validated['is_active'])) {
                        $updateData['is_active'] = $validated['is_active'];
                    }

                    // Update password if provided
                    if (!empty($validated['password'])) {
                        $updateData['password'] = bcrypt($validated['password']);
                    }

                    $user->update($updateData);

                    return ['user' => $user->fresh(), 'action' => 'updated'];
                } else {
                    // User doesn't exist - create
                    // Require password for new user
                    if (empty($validated['password'])) {
                        throw new \Exception('Password is required when creating a new user');
                    }

                    $user = User::create([
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'password' => bcrypt($validated['password']),
                        'role' => $validated['role'],
                        'is_active' => $validated['is_active'] ?? true,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]);

                    return ['user' => $user, 'action' => 'created'];
                }
            });

            $user = $result['user'];
            $action = $result['action'];

            $message = $action === 'created'
                ? 'User created successfully for tenant'
                : 'User updated successfully for tenant';

            return ApiResponse::send($message, $action === 'created' ? 201 : 200, [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'action' => $action,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to create/update user: ' . $e->getMessage());
        }
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

    /**
     * Format migration output for clean JSON response
     */
    private function formatMigrationOutput(string $output): array
    {
        // Remove ANSI color codes
        $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

        // Split by lines and remove empty lines
        $lines = array_filter(array_map('trim', explode("\n", $cleanOutput)));

        $migrations = [];
        $info = '';

        foreach ($lines as $line) {
            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Check if it's an INFO line
            if (stripos($line, 'INFO') !== false) {
                $info = trim(str_replace('INFO', '', $line));
                continue;
            }

            // Parse migration lines (format: "migration_name .... time DONE")
            if (preg_match('/^(.+?)\s+\.+\s+(.+?)\s+(DONE|FAIL|PENDING)$/i', $line, $matches)) {
                $migrations[] = [
                    'migration' => trim($matches[1]),
                    'duration' => trim($matches[2]),
                    'status' => strtoupper(trim($matches[3])),
                ];
            }
        }

        return [
            'summary' => $info ?: 'Migrations executed',
            'migrations' => $migrations,
            'total_migrations' => count($migrations),
            'raw_output' => $cleanOutput,
        ];
    }
}
