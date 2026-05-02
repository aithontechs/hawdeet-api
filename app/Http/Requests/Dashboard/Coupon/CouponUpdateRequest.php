<?php

namespace App\Http\Requests\Dashboard\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true ;
    }

    public function rules()
    {
        return [
            'code' => ['sometimes','string','max:255',Rule::unique('coupons', 'code')->ignore($this->coupon),],
            'discount_type' => ['sometimes', 'in:fixed,percentage'],
            'discount_value' => ['sometimes','numeric','min:1',
                function ($attr, $value, $fail) {
                    if (request('discount_type') === 'percentage' && $value > 100) {
                        $fail('Percentage cannot exceed 100%');
                    }
                }
            ],
            'start_at' => ['sometimes', 'date', 'after_or_equal:today'],
            'end_at' => ['sometimes', 'date', 'after:start_at'],
            'max_uses' => ['sometimes', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:1'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
