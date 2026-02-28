<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TenantMigrationController extends Controller
{
    /**
     * Run migrations for the landlord database.
     */
    public function runLandlordMigrations()
    {
        try {
            Log::info('Starting landlord migrations', [
                'database'    => 'mysql',
                'path'        => 'database/migrations/landlord',
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp'   => now()->toDateTimeString(),
            ]);

            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path'     => 'database/migrations/landlord',
                '--force'    => true,
            ]);

            $output = Artisan::output();
            $formattedOutput = $this->formatMigrationOutput($output);

            Log::info('Landlord migrations completed successfully', [
                'total_migrations' => $formattedOutput['total_migrations'],
                'migrations'       => $formattedOutput['migrations'],
                'executed_by'      => auth()->user()?->email ?? 'System',
                'timestamp'        => now()->toDateTimeString(),
            ]);

            return ApiResponse::send('Landlord migrations executed successfully', 200, $formattedOutput);

        } catch (\Exception $e) {
            Log::error('Landlord migrations failed', [
                'error'       => $e->getMessage(),
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp'   => now()->toDateTimeString(),
            ]);

            return ApiResponse::serverError('Failed to run landlord migrations: ' . $e->getMessage());
        }
    }

    /**
     * Run migrations for a specific tenant.
     */
    public function runTenantMigrations(Tenant $tenant)
    {
        try {
            Log::info('Starting migrations for specific tenant', [
                'tenant_id'     => $tenant->id,
                'tenant_name'   => $tenant->name,
                'tenant_domain' => $tenant->domain,
                'executed_by'   => auth()->user()?->email ?? 'System',
                'timestamp'     => now()->toDateTimeString(),
            ]);

            Artisan::call('tenants:artisan', [
                'artisanCommand' => 'migrate --force',
                '--tenant'       => $tenant->id,
            ]);

            $output = Artisan::output();
            $formattedOutput = $this->formatMigrationOutput($output);

            Log::info('Tenant migrations completed successfully', [
                'tenant_id'        => $tenant->id,
                'tenant_name'      => $tenant->name,
                'total_migrations' => $formattedOutput['total_migrations'],
                'migrations'       => $formattedOutput['migrations'],
                'executed_by'      => auth()->user()?->email ?? 'System',
                'timestamp'        => now()->toDateTimeString(),
            ]);

            return ApiResponse::send('Migrations executed successfully', 200, $formattedOutput);

        } catch (\Exception $e) {
            Log::error('Tenant migrations failed', [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
                'error'       => $e->getMessage(),
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp'   => now()->toDateTimeString(),
            ]);

            return ApiResponse::serverError('Failed to run migrations: ' . $e->getMessage());
        }
    }

    /**
     * Run migrations for all active tenants.
     */
    public function runAllTenantsMigrations()
    {
        try {
            $tenants = Tenant::where('is_active', true)->get();

            Log::info('Starting migrations for all tenants', [
                'total_tenants' => count($tenants),
                'tenant_ids'    => $tenants->pluck('id')->toArray(),
                'executed_by'   => auth()->user()?->email ?? 'System',
                'timestamp'     => now()->toDateTimeString(),
            ]);

            $results      = [];
            $successCount = 0;
            $failedCount  = 0;

            foreach ($tenants as $tenant) {
                try {
                    Artisan::call('tenants:artisan', [
                        'artisanCommand' => 'migrate --force',
                        '--tenant'       => $tenant->id,
                    ]);

                    $output        = Artisan::output();
                    $formattedOutput = $this->formatMigrationOutput($output);

                    $results[] = [
                        'tenant_id'   => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'status'      => 'success',
                        'migrations'  => $formattedOutput,
                    ];

                    Log::info('Tenant migrations completed', [
                        'tenant_id'        => $tenant->id,
                        'tenant_name'      => $tenant->name,
                        'total_migrations' => $formattedOutput['total_migrations'],
                    ]);

                    $successCount++;

                } catch (\Exception $e) {
                    $results[] = [
                        'tenant_id'   => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'status'      => 'failed',
                        'error'       => $e->getMessage(),
                    ];

                    Log::error('Tenant migrations failed', [
                        'tenant_id'   => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'error'       => $e->getMessage(),
                    ]);

                    $failedCount++;
                }
            }

            Log::info('All tenants migrations process completed', [
                'total_tenants' => count($tenants),
                'successful'    => $successCount,
                'failed'        => $failedCount,
                'executed_by'   => auth()->user()?->email ?? 'System',
                'timestamp'     => now()->toDateTimeString(),
            ]);

            return ApiResponse::send('Migrations executed for all tenants', 200, [
                'total_tenants' => count($tenants),
                'successful'    => $successCount,
                'failed'        => $failedCount,
                'results'       => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to run migrations for all tenants', [
                'error'       => $e->getMessage(),
                'executed_by' => auth()->user()?->email ?? 'System',
                'timestamp'   => now()->toDateTimeString(),
            ]);

            return ApiResponse::serverError('Failed to run migrations for all tenants: ' . $e->getMessage());
        }
    }

    private function formatMigrationOutput(string $output): array
    {
        $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
        $lines       = array_filter(array_map('trim', explode("\n", $cleanOutput)));

        $migrations = [];
        $info       = '';

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            if (stripos($line, 'INFO') !== false) {
                $info = trim(str_replace('INFO', '', $line));
                continue;
            }

            if (preg_match('/^(.+?)\s+\.+\s+(.+?)\s+(DONE|FAIL|PENDING)$/i', $line, $matches)) {
                $migrations[] = [
                    'migration' => trim($matches[1]),
                    'duration'  => trim($matches[2]),
                    'status'    => strtoupper(trim($matches[3])),
                ];
            }
        }

        return [
            'summary'          => $info ?: 'Migrations executed',
            'migrations'       => $migrations,
            'total_migrations' => count($migrations),
            'raw_output'       => $cleanOutput,
        ];
    }
}
