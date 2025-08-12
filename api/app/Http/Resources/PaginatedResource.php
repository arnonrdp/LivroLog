<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedResource extends JsonResource
{
    public function toArray($request)
    {
        $pagination = $this->resource;

        return [
            'data' => $pagination->items(),
            'meta' => [
                'current_page' => $pagination->currentPage(),
                'from' => $pagination->firstItem(),
                'last_page' => $pagination->lastPage(),
                'per_page' => $pagination->perPage(),
                'to' => $pagination->lastItem(),
                'total' => $pagination->total(),
            ],
        ];
    }
}
