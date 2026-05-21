<?php

namespace App\Http\Requests\Dashboard\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class UserSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        return [
            'user_id' =>'required|exists:users,id',
            'plan_id' => 'required|exists:subscription_plans,id',
            'price' => 'nullable|numeric|min:0' ,
            'status' => 'sometimes|in:active,inactive',
            'payment_status' => 'sometimes|in:gift',
        ];
    }
}
