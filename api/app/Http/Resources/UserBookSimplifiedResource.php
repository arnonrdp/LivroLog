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
            'amazon_asin' => $this->amazon_asin,
            'asin_status' => $this->asin_status,
            'asin_processed_at' => $this->asin_processed_at,
            'title' => $this->title,
            'authors' => $this->authors,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'pivot' => [
                'user_id' => $this->pivot->user_id,
                'book_id' => $this->pivot->book_id,
                'added_at' => $this->pivot->added_at,
                'read_at' => $this->pivot->read_at,
                'is_private' => (bool) $this->pivot->is_private,
                'reading_status' => $this->pivot->reading_status,
                'created_at' => $this->pivot->created_at,
                'updated_at' => $this->pivot->updated_at,
            ],
        ];
    }
}
