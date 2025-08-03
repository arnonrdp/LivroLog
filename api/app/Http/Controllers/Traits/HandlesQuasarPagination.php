<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

trait HandlesQuasarPagination
{
    /**
     * Applies pagination parameters to a query from request.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function applyPagination($query, Request $request)
    {
        $page = $request->input('pagination.page', 1);
        $perPage = $request->input('pagination.rowsPerPage', 20);
        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
