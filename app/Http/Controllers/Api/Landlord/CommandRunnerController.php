<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CommandRunnerController
{
    public function queueStatus(): JsonResponse
    {
        $now = time();

        $pending = DB::table('jobs')->whereNull('reserved_at')->count();

        $stuck = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $now - 300)
            ->count();

        $failedCount = DB::table('failed_jobs')->count();
        $lastFailed  = DB::table('failed_jobs')->latest('failed_at')->value('failed_at');

        $oldestPendingTimestamp = DB::table('jobs')->whereNull('reserved_at')->min('created_at');
        $oldestPendingAgeSeconds = $oldestPendingTimestamp ? ($now - $oldestPendingTimestamp) : null;

        $workerHealthy = $oldestPendingTimestamp === null || $oldestPendingAgeSeconds < 120;

        return ApiResponse::send('Queue status retrieved', 200, [
            'worker_healthy'        => $workerHealthy,
            'pending_jobs'          => $pending,
            'stuck_jobs'            => $stuck,
            'failed_jobs'           => $failedCount,
            'last_failed_at'        => $lastFailed,
            'oldest_pending_age_seconds' => $oldestPendingAgeSeconds,
        ]);
    }

    public function autoLogoutAllUsers(): JsonResponse
    {
        Artisan::call('users:auto-logout');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function reconcileGasStationBalances(): JsonResponse
    {
        Artisan::call('gas-stations:reconcile-balances');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function capitalSnapshot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year'  => 'nullable|integer|min:2000|max:2100',
        ]);

        $options = array_filter([
            '--month' => $validated['month'] ?? null,
            '--year'  => $validated['year'] ?? null,
        ], fn($v) => $v !== null);

        Artisan::call('capital:snapshot', $options);

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function calculateMonthlyClosingBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenants'   => 'nullable|array',
            'tenants.*' => 'integer',
        ]);

        Artisan::call('customers:calculate-monthly-closing', [
            '--tenant' => $validated['tenants'] ?? [],
        ]);

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function calculateYearlyClosingBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenants'   => 'nullable|array',
            'tenants.*' => 'integer',
            'year'      => 'nullable|integer|min:2000|max:2100',
        ]);

        $options = ['--tenant' => $validated['tenants'] ?? []];
        if (!empty($validated['year'])) {
            $options['--year'] = $validated['year'];
        }

        Artisan::call('customers:calculate-yearly-closing', $options);

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function pruneOrphanedSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delete' => 'nullable|boolean',
        ]);

        Artisan::call('settings:prune-orphaned', [
            '--delete' => $validated['delete'] ?? false,
        ]);

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function cleanupActivityLogs(): JsonResponse
    {
        Artisan::call('activitylog:cleanup', ['--force' => true]);

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function cleanupOrphanedFiles(): JsonResponse
    {
        Artisan::call('documents:cleanup-orphaned');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function scheduleDocumentCleanup(): JsonResponse
    {
        Artisan::call('documents:schedule-cleanup');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function backupAllTenants(): JsonResponse
    {
        Artisan::call('backup:all-tenants');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function backupRetentionCleanup(): JsonResponse
    {
        Artisan::call('backup:retention-cleanup');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function mirrorAllTenants(): JsonResponse
    {
        Artisan::call('mirror:all-tenants');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function analyzeApiHits(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant' => 'nullable|string|max:100',
        ]);

        Artisan::call('api:analyze', array_filter([
            'tenant' => $validated['tenant'] ?? null,
        ], fn($v) => $v !== null));

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    // ── Laravel built-in maintenance commands ──────────────────────────────────

    public function optimizeClear(): JsonResponse
    {
        Artisan::call('optimize:clear');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function cacheClear(): JsonResponse
    {
        Artisan::call('cache:clear');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function configClear(): JsonResponse
    {
        Artisan::call('config:clear');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function configCache(): JsonResponse
    {
        Artisan::call('config:cache');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function routeClear(): JsonResponse
    {
        Artisan::call('route:clear');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function routeCache(): JsonResponse
    {
        Artisan::call('route:cache');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function viewClear(): JsonResponse
    {
        Artisan::call('view:clear');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function storageLink(): JsonResponse
    {
        Artisan::call('storage:link');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function queueRetryAll(): JsonResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return ApiResponse::send(trim(Artisan::output()), 200);
    }

    public function queueFlush(): JsonResponse
    {
        Artisan::call('queue:flush');

        return ApiResponse::send(trim(Artisan::output()), 200);
    }
}
