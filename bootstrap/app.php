<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Daily refresh of all customer balances at 23:59 (incremental - last 1 month)
        $schedule->call(function () {
            \App\Services\Customers\CustomerBalanceService::refreshAllCustomerBalancesDaily();
        })
            ->daily()
            ->at('23:59')
            ->withoutOverlapping()
            ->runInBackground();

        // Calculate monthly closing balance at 00:01 on the 1st of each month
        $schedule->command('customers:calculate-monthly-closing')
            ->monthlyOn(1, '00:01')
            ->withoutOverlapping()
            ->runInBackground();

        // Calculate yearly closing balance at 00:59 on January 1st of each year
        $schedule->command('customers:calculate-yearly-closing')
            ->yearlyOn(1, 1, '00:59')
            ->withoutOverlapping()
            ->runInBackground();

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
