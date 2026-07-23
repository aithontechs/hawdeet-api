<?php

namespace App\Http\Requests\Application\Checkout ;

use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\Rules\Phone;

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
                (new Phone())->international()
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone') && str_starts_with($this->phone, '0')) {
            $this->merge([
                'phone' => '+2' . ltrim($this->phone, '0'),
            ]);
        }
    }
}
