<?php

namespace App\Console\Commands\Landlord;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueStatusCommand extends Command
{
    protected $signature = 'queue:status';

    protected $description = 'Show queue health: pending jobs, stuck jobs, failed jobs, and last activity';

    public function handle(): int
    {
        $now = time();

        $pending = DB::table('jobs')->whereNull('reserved_at')->count();

        // Jobs reserved more than 5 minutes ago are likely stuck
        $stuckThreshold = $now - 300;
        $stuck = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $stuckThreshold)
            ->count();

        $failedCount = DB::table('failed_jobs')->count();
        $lastFailed  = DB::table('failed_jobs')->latest('failed_at')->value('failed_at');

        $oldestPendingTimestamp = DB::table('jobs')->whereNull('reserved_at')->min('created_at');
        $oldestPendingAge       = $oldestPendingTimestamp
            ? round(($now - $oldestPendingTimestamp) / 60, 1) . ' min ago'
            : null;

        // A pending job older than 2 minutes suggests the scheduler/worker isn't firing
        $workerHealthy = $oldestPendingTimestamp === null || ($now - $oldestPendingTimestamp) < 120;

        $this->line('');
        $this->line('Queue Status');
        $this->line('────────────────────────────────');
        $this->line("Worker health : " . ($workerHealthy ? '✓ OK' : '✗ WARNING — jobs not being picked up'));
        $this->line("Pending jobs  : {$pending}");
        $this->line("Stuck jobs    : {$stuck}" . ($stuck > 0 ? ' (reserved > 5 min ago)' : ''));
        $this->line("Failed jobs   : {$failedCount}" . ($lastFailed ? " (last: {$lastFailed})" : ''));
        $this->line("Oldest pending: " . ($oldestPendingAge ?? 'none'));
        $this->line('');

        return self::SUCCESS;
    }
}
