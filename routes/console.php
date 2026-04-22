<?php

use App\Console\Commands\BackupAllTenantsCommand;
use App\Console\Commands\BackupRetentionCleanupCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily tenant backups at 02:00 — runs synchronously, no queue worker needed
Schedule::command(BackupAllTenantsCommand::class)->dailyAt('02:00');

// GFS retention cleanup at 03:00 — runs after backups complete
Schedule::command(BackupRetentionCleanupCommand::class)->dailyAt('03:00');
