<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;


class MacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerCollectionMacros();
        $this->registerRequestMacros();
        $this->registerQueryMancro();
        $this->registerBluePrintMacro();
    }

    private function registerCollectionMacros()
    {
        Collection::macro('toUpper', function () {
            /** @var \Illuminate\Support\Collection $this */
            return $this->map(fn(string $value) => strtoupper($value));
        });

        /**
         * $collection = collect(['hello', 'world']);
         * $upper = $collection->toUpper(); // ['HELLO', 'WORLD']
         * 
         */
    }


    private function registerRequestMacros()
    {
        Request::macro('hasAnyFilled', function (array $keys) {
            return collect($keys)->contains(function ($key) {
                /** @var \Illuminate\Http\Request $this */
                return $this->filled($key);
            });
        });

        /**
         * if ($request->hasAnyFilled(['name', 'email', 'phone'])) {}
         * 
         */
    }

    private function registerBluePrintMacro()
    {
        // Money fields (large integer part + 8 decimals)
        Blueprint::macro('money', function (string $column) {
            /** @var \Illuminate\Database\Schema\Blueprint $this */
            return $this->decimal($column, 45, 8);
        });

        // Exchange rates (huge integer + 10 decimals)
        Blueprint::macro('rate', function (string $column) {
            /** @var \Illuminate\Database\Schema\Blueprint $this */
            return $this->decimal($column, 30, 8);
        });

        // Quantities (large integer + 6 decimals)
        Blueprint::macro('quantity', function (string $column) {
            /** @var \Illuminate\Database\Schema\Blueprint $this */
            return $this->decimal($column, 20, 2);
        });

        // Quantities (large integer + 6 decimals)
        Blueprint::macro('percent', function (string $column) {
            /** @var \Illuminate\Database\Schema\Blueprint $this */
            return $this->decimal($column, 8, 2);
        });
    }

    private function registerQueryMancro()
    {
        Builder::macro('whereDateBetween', function ($column, $start, $end) {
            /** @var \Illuminate\Database\Query\Builder $this */
            return $this->whereDate($column, '>=', $start)
                        ->whereDate($column, '<=', $end);
        });
    }
}
