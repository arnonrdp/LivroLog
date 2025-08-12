<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedUserResource extends JsonResource
{
    public function toArray($request)
    {
        $pagination = $this->resource;

        return [
            'data' => UserResource::collection($pagination->items()),
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

    public static $wrap = null; // Force no additional wrapping
}
