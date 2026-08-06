<?php

namespace App\Http\Requests\Application\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

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
            'phone' => ['sometimes', (new Phone())->international(),Rule::unique('users', 'phone')->ignore(auth()->id()),],
            'birth_date' => ['sometimes', 'date' , 'before_or_equal:' . now()->subYears(8)->toDateString(), ],
            'avatar_url' => 'sometimes|image|max:5120',
            'bio' => 'sometimes|string|max:1000',
        ];
    }

    public function messages()
    {
        return [
            'phone.phone' => 'رقم الهاتف غير صحيح. أدخل الرقم بصيغة دولية تبدأ بـ + وكود الدولة (مثال: +201012345678).',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.unique' => 'رقم الهاتف مستخدم من قبل.',
            'birth_date.before_or_equal' => 'يجب ألا يقل العمر عن 8 سنوات.',
        ];
    }
}
