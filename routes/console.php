<?php

use App\Console\Commands\BackupAllTenantsCommand;
use App\Console\Commands\BackupRetentionCleanupCommand;
use App\Console\Commands\MirrorAllTenantsCommand;
use App\Jobs\RecalculateAllSalesProfitJob;
use App\Models\Tenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Queue ──────────────────────────────────────────────────────────────────────
// Process queued jobs every minute. --stop-when-empty exits after draining the
// queue instead of running as a daemon — safe for shared hosting cron.
Schedule::command('queue:work --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// ── Auth ───────────────────────────────────────────────────────────────────────
Schedule::command('users:auto-logout')
    ->daily()
    ->at('01:00')
    ->name('auto-logout-all-users')
    ->withoutOverlapping()
    ->onOneServer();

// ── Capital ────────────────────────────────────────────────────────────────────
Schedule::command('capital:snapshot')
    ->monthlyOn(1, '00:30')
    ->name('take-capital-snapshot')
    ->withoutOverlapping()
    ->runInBackground();

// ── Customers ──────────────────────────────────────────────────────────────────
// Schedule::command('customers:calculate-monthly-closing')
//     ->monthlyOn(1, '00:01')
//     ->name('calculate-monthly-closing-balance')
//     ->withoutOverlapping()
//     ->runInBackground();

// Schedule::command('customers:calculate-yearly-closing')
//     ->yearlyOn(1, 1, '00:59')
//     ->name('calculate-yearly-closing-balance')
//     ->withoutOverlapping()
//     ->runInBackground();

// ── Sales profit ───────────────────────────────────────────────────────────────
// The queue is tenant-aware, so each job must be dispatched with a tenant
// current — dispatching from the scheduler directly would fail with no tenant context.
Schedule::call(function () {
    Tenant::runForEachActive('Sale profit recalculation dispatch', function () {
        $enabled = \App\Helpers\FeatureHelper::isSaleProfitRecalculation();
        \App\Helpers\FeatureHelper::flush();

        if (!$enabled) {
            return ['skipped' => 'sale_profit_recalculation feature disabled'];
        }

        RecalculateAllSalesProfitJob::dispatch();
    });
})
->weekly()
->sundays()
->at('02:00')
->name('recalculate-all-sales-profit')
->withoutOverlapping()
->onOneServer();

// ── Vehicle ────────────────────────────────────────────────────────────────────
Schedule::command('gas-stations:reconcile-balances')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->name('reconcile-gas-station-balances')
    ->withoutOverlapping()
    ->onOneServer();

// ── Activity log ───────────────────────────────────────────────────────────────
if (config('activitylog.auto_cleanup')) {
    Schedule::command('activitylog:cleanup --force')
        ->daily()
        ->at('02:00')
        ->name('cleanup-old-activity-logs')
        ->withoutOverlapping()
        ->onOneServer();
}

// ── Backups ────────────────────────────────────────────────────────────────────
Schedule::command(BackupAllTenantsCommand::class)
    ->hourly()
    ->name('backup-all-tenants')
    ->withoutOverlapping();

Schedule::command(BackupRetentionCleanupCommand::class)
    ->hourlyAt(30)
    ->name('backup-retention-cleanup')
    ->withoutOverlapping();

// ── Mirror ─────────────────────────────────────────────────────────────────────
Schedule::command(MirrorAllTenantsCommand::class)
    ->everyThirtyMinutes()
    ->name('mirror-all-tenants')
    ->withoutOverlapping()
    ->runInBackground();
