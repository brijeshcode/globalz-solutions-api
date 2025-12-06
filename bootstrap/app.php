<?php

use App\Http\Controllers\Api\Customers\CustomerStatmentController;
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
        // Recalculate all customer balances daily at midnight
        $schedule->call(function () {
            $statementController = app(CustomerStatmentController::class);
            $result = $statementController->processBalanceRecalculation();

            \Illuminate\Support\Facades\Log::info('Daily customer balance recalculation completed', $result);
        })
        ->daily()
        ->at('00:00')
        ->name('recalculate-customer-balances')
        ->withoutOverlapping()
        ->onOneServer();

        // Calculate monthly closing balance at 00:01 on the 1st of each month
        // $schedule->command('customers:calculate-monthly-closing')
        //     ->monthlyOn(1, '00:01')
        //     ->name('calculate-monthly-closing-balance')
        //     ->withoutOverlapping()
        //     ->runInBackground();

        // Calculate yearly closing balance at 00:59 on January 1st of each year
        // $schedule->command('customers:calculate-yearly-closing')
        //     ->yearlyOn(1, 1, '00:59')
        //     ->name('calculate-yearly-closing-balance')
        //     ->withoutOverlapping()
        //     ->runInBackground();

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
