<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Serialize every Carbon/date to JSON as a plain "Y-m-d H:i:s" string in the
        // app/tenant timezone (config('app.timezone'), set per-tenant on boot) — with
        // no trailing `Z`. Laravel's default emits a UTC-marked ISO string, which makes
        // the frontend timezone-shift transaction dates to the next calendar day. This
        // hook covers Carbon values passed straight into API Resources (e.g. SaleResource
        // `'date' => $this->date`), which bypass the model's serializeDate().
        $serializer = fn ($date) => $date->format('Y-m-d H:i:s');
        Carbon::serializeUsing($serializer);
        CarbonImmutable::serializeUsing($serializer);

        DB::listen(function ($query) {
            if ($query->time > 100) {
                Log::channel('slow_queries')->warning('Slow query detected', [
                    'sql' => $query->sql,
                    'time' => $query->time . 'ms',
                    'bindings' => $query->bindings,
                ]);
            }
        });
    }
}
