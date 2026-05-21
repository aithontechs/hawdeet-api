<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSubscriptionResource extends JsonResource
{
public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user?->id,
            'name' => $this->user?->name,
            'plan' => $this->plan?->name,

            'subscription_period' => [
                'start_at' => $this->start_at?->format('Y-m-d'),
                'end_at' => $this->end_at?->format('Y-m-d'),
            ],
            'price' => (float) $this->price,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'remaining_days' => now()->diffInDays($this->end_at, false),
            'is_expired' => now()->gt($this->end_at),
            'canceled_at' => $this->canceled_at,
            'ended_reason' => $this->ended_reason,
            'created_at' => $this->created_at?->format('Y-m-d h:i A'),
        ];
    }
}
