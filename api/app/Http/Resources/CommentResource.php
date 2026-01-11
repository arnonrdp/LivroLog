<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'user' => [
                'id' => $this->user?->id,
                'display_name' => $this->user?->display_name,
                'username' => $this->user?->username,
                'avatar' => $this->user?->avatar,
            ],
            'activity_id' => $this->activity_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_owner' => $request->user()?->id === $this->user_id,
        ];
    }
}
