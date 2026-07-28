<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait HandlesPagination
{
    /**
     * Applies pagination parameters to a query from request.
     *
     * @param  Builder  $query
     * @return LengthAwarePaginator
     */
    public function applyPagination($query, Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
