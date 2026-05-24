<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'start_at'       => $this->start_at,
            'end_at'         => $this->end_at,
            'price'          => $this->price,
            'status'         => $this->status,
            'payment_status' => $this->payment_status,
            'canceled_at'    => $this->canceled_at,
            'ended_reason'   => $this->ended_reason,
        ];
    }
}
