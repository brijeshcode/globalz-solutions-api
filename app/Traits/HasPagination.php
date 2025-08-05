<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait HasPagination
{
    /**
     * Apply pagination to the query
     */
    protected function applyPagination(Builder $query, Request $request): LengthAwarePaginator
    {
        $perPage = $this->getPerPage($request);
        
        return $query->paginate($perPage);
    }

    /**
     * Get per page value from request with validation using config
     */
    protected function getPerPage(Request $request): int
    {
        $defaultPerPage = config('pagination.default_per_page', 20);
        $maxPerPage = config('pagination.max_per_page', 2000);
        
        $perPage = (int) $request->input('per_page', $defaultPerPage);
        
        // Ensure it's at least 1
        if ($perPage < 1) {
            $perPage = $defaultPerPage;
        }
        
        // Ensure it doesn't exceed maximum
        if ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }
        
        return $perPage;
    }
}