<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'type' => $this->type,
            'status' => $this->status,
            'payment_gateway' => $this->payment_gateway,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'subscription_id' => $this->user_subscription_id,
            'transaction_id' => $this->gateway_transaction_id,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
        ];
    }
}
