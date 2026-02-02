<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait HasDateFilters
{
    public function scopeFromDate( Builder $query, $fromDate, string $column = 'date') {
        if (!$fromDate) {
            return $query;
        }

        return $query->where( $column, '>=', Carbon::parse($fromDate)->startOfDay() );
    }

    public function scopeToDate( Builder $query, $toDate, string $column = 'date' ) {
        if (!$toDate) {
            return $query;
        }

        return $query->where(
            $column,
            '<=',
            Carbon::parse($toDate)->endOfDay()
        );
    }
}
