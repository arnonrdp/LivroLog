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
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'pivot' => [
                'read_at' => $this->pivot->read_at,
            ],
        ];
    }
}
