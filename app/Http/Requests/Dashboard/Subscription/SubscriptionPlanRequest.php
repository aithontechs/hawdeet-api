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
                'name' => 'sometimes|string|max:255',
                'duration_months' => 'sometimes|integer|min:1',
                'price'=> 'sometimes|numeric|min:1',
                'compare_price' => 'nullable|numeric|min:1|gt:price',
                'description' => 'sometimes|string',
                'is_active' => 'nullable|boolean'
            ];
        }

        return [
            'name' => 'required|string|max:255',
            'duration_months' => 'required|integer|min:1',
            'price'=> 'required|numeric|min:1',
            'compare_price' => 'nullable|numeric|min:1|gt:price',
            'description' => 'required|string',
            'is_active' => 'nullable|boolean'
        ];
    }
}
