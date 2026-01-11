<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'type' => $this->type,
            'actor' => [
                'id' => $this->actor?->id,
                'display_name' => $this->actor?->display_name,
                'username' => $this->actor?->username,
                'avatar' => $this->actor?->avatar,
            ],
            'data' => $this->data,
            'read_at' => $this->read_at,
            'is_read' => ! is_null($this->read_at),
            'created_at' => $this->created_at,
        ];
    }
}
