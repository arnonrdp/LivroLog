<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserWithBooksResource extends JsonResource
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
            'display_name' => $this->display_name,
            'username' => $this->username,
            'avatar' => $this->avatar,
            'shelf_name' => $this->shelf_name,
            'locale' => $this->locale,
            'preferred_amazon_region' => $this->preferred_amazon_region,
            'is_private' => $this->is_private,
            'role' => $this->role,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'books' => $this->when($this->relationLoaded('books'),
                UserBookSimplifiedResource::collection($this->books)),
        ];
    }
}
