<?php

use App\Jobs\RecalculateAllSalesProfitJob;
use App\Models\Tenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recalculate sale profits weekly to keep cost-based profit figures accurate
// after purchase expenses are settled. The queue is tenant-aware, so each job
// must be dispatched with a tenant current — dispatching from the scheduler
// directly would fail with no tenant context.
Schedule::call(function () {
    Tenant::runForEachActive('Sale profit recalculation dispatch', fn () => RecalculateAllSalesProfitJob::dispatch());
})
->weekly()
->sundays()
->at('02:00')
->name('recalculate-all-sales-profit')
->withoutOverlapping()
->onOneServer();
