<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'status' => $this->status,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'max_uses' => $this->max_uses ,
            'used_count' => $this->used_count,
            'min_order_amount' => $this->min_order_amount,
            'usages' => CouponUsageResource::collection($this->whenLoaded('coupon_usages')),
        ];
    }
}
