<?php

namespace App\Providers;

use App\Models\Items\Item;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Relation::morphMap([
            'purchase'      => Purchase::class,
            'purchase_item' => PurchaseItem::class,
            'initial'       => Item::class,
        ]);

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
