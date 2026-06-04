<?php

namespace App\Http\Requests\Application\Checkout ;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'payment_method'    => ['required', 'in:card,wallet'],
            'coupon_code'       => ['nullable', 'string'],

            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'email'      => ['nullable', 'email'],
            'phone' => [
                'required_if:payment_method,wallet',
                'nullable',
                'digits:11',
            ],
        ];
    }
}
