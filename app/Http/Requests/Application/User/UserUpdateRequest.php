<?php

namespace App\Http\Requests\Application\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:30|min:4',
            'phone' => ['sometimes', 'regex:/^01[0125][0-9]{8}$/' ,  Rule::unique('users', 'phone')->Ignore(auth()->id())],
            'birth_date' => ['sometimes', 'date' , 'before_or_equal:' . now()->subYears(8)->toDateString(), ],
            'avatar_url' => 'sometimes|image|max:5120',
            'bio' => 'sometimes|string|max:1000',
            'preferred_currency' => 'sometimes|in:EGP,USD'
        ];
    }

    public function messages()
    {
        return [
            'phone.regex' => 'رقم الهاتف يجب أن يكون رقم محمول مصري صحيح.',
            'birth_date.before_or_equal' => 'يجب ألا يقل العمر عن 8 سنوات.',
        ];
    }
}
