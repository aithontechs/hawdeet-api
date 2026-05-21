<?php

namespace App\Http\Requests\Application\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionStoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'payment_method' => ['required', 'in:card,wallet'],
            'first_name'     => ['nullable', 'string', 'max:100'],
            'last_name'      => ['nullable', 'string', 'max:100'],
            'email'          => ['nullable', 'email'],
            'phone' => ['required_if:payment_method,wallet','nullable','digits:11'],
        ];
    }
}
