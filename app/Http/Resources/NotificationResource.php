<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title' => $this->data['title'] ?? null,
            'message'    => $this->data['message'],
            'type'       => $this->data['type'],
            'reference_id' => $this->data['reference_id'] ?? null ,
            'is_read'    => !is_null($this->read_at),
            'read_at' => $this->read_at?->diffForHumans(),
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
