<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'books_count' => $this->when(isset($this->books_count), $this->books_count),
            'followers_count' => $this->when(isset($this->followers_count), $this->followers_count),
            'following_count' => $this->when(isset($this->following_count), $this->following_count),
            'is_following' => $this->when(isset($this->is_following), (bool) $this->is_following),
            'has_pending_follow_request' => $this->when(isset($this->has_pending_follow_request), (bool) $this->has_pending_follow_request),
        ];
    }
}
