<?php

namespace App\Http\Requests\Dashboard\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class CouponStoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true ;
    }


    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'unique:coupons,code'],
            'discount_type' => ['required', 'in:fixed,percentage'],
            'discount_value' => ['required', 'numeric', 'min:1' ,
                        function($attr , $value , $fail){
                            if(request('discount_type') === 'percentage'  && $value > 100)
                            {
                                $fail('Percentage cannot exceed 100%');
                            }

                        }
                ],
            'start_at' => ['required', 'date' , 'after_or_equal:today'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'max_uses' => ['required', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:1'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
