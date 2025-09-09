<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserBookSimplifiedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'isbn' => $this->isbn,
            'title' => $this->title,
            'authors' => $this->authors,
            'thumbnail' => $this->thumbnail,
            'asin_status' => $this->asin_status,
            'formatted_published_date' => $this->formatted_published_date,
            'average_rating' => $this->average_rating,
            'reviews_count' => $this->reviews_count,
            'pivot' => [
                'added_at' => $this->pivot->added_at ?? null,
                'read_at' => $this->pivot->read_at ?? null,
                'reading_status' => $this->pivot->reading_status ?? null,
                'is_private' => $this->pivot->is_private ?? 0,
            ],
        ];
    }
}
