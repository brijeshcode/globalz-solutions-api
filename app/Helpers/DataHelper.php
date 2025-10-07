<?php

namespace App\Helpers;

use App\Models\Employees\Employee;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DataHelper {

    /**
     * Create a custom paginator from a collection
     *
     * @param Collection|array $items The items to paginate
     * @param int $perPage Items per page
     * @param int|null $currentPage Current page number
     * @param Request|null $request Request object for URL and query parameters
     * @return LengthAwarePaginator
     */
    public static function customPaginate(
        $items,
        int $perPage,
        ?int $currentPage = null,
        ?Request $request = null
    ): LengthAwarePaginator {
        $items = $items instanceof Collection ? $items : collect($items);
        $currentPage = $currentPage ?? request()->get('page', 1);
        $request = $request ?? request();

        $total = $items->count();

        return new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage),
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * Create an empty paginator
     *
     * @param int $perPage Items per page
     * @param Request|null $request Request object for URL and query parameters
     * @return LengthAwarePaginator
     */
    public static function emptyPaginate(int $perPage, ?Request $request = null): LengthAwarePaginator
    {
        $request = $request ?? request();

        return new LengthAwarePaginator(
            [],
            0,
            $perPage,
            1,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}