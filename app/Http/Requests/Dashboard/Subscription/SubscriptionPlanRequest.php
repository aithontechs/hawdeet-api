<?php

namespace App\Http\Requests\Dashboard\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionPlanRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        if($this->isMethod('put') || $this->isMethod('patch'))
        {
            return [
                'name' => 'sometimes|string|min:2|max:50',
                'duration_months' => 'sometimes|integer|min:1',
                'price'=> 'sometimes|numeric|min:1',
                'price_usd' => 'sometimes|numeric|min:1',
                'compare_price' => 'nullable|numeric|min:1|gt:price',
                'compare_price_usd' => 'nullable|numeric|min:1|gt:price_usd',
                'description' => 'sometimes|array|min:1',
                'description.*' => 'required|string|max:100',
                'is_active' => 'nullable|boolean'
            ];
        }

        return [
            'name' => 'required|string|min:2|max:50',
            'duration_months' => 'required|integer|min:1',
            'price'=> 'required|numeric|min:1',
            'price_usd'         => 'required|numeric|min:1',
            'compare_price' => 'nullable|numeric|min:1|gt:price',
            'compare_price_usd' => 'nullable|numeric|min:1|gt:price_usd',
            'description' => 'required|array|min:1',
            'description.*' => 'required|string|max:100',
            'is_active' => 'nullable|boolean'
        ];
    }
}
