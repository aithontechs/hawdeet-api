<?php

namespace App\Http\Requests\Dashboard\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true ;
    }


    public function rules(): array
    {
        $userId = $this->route('user') ;
        return [
            'name' => $this->isMethod('post') ? 'required|string|max:255' : 'sometimes|string|max:255',
            'email' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => 'nullable|regex:/^01[0125][0-9]{8}$/|unique:users,phone',
            'password' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'max:25',
                Password::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'birth_date' => $this->isMethod('post') ? ['required','date' ,  'before_or_equal:' . now()->subYears(8)->toDateString(), ] : ['sometimes', 'date' ,  'before_or_equal:' . now()->subYears(8)->toDateString(), ],
            'is_author' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

        public function messages()
        {
            return [
                'phone.regex' => 'رقم الهاتف يجب أن يكون رقم محمول مصري صحيح.',
                'birth_date.before_or_equal' => 'يجب ألا يقل العمر عن 8 سنوات.',
                'password.min' => 'كلمة المرور يجب أن تحتوي على 12 أحرف على الأقل.',
                'password.max' => 'كلمة المرور يجب ألا تزيد عن 50 حرفًا.',
                'password.mixed' => 'كلمة المرور يجب أن تحتوي على حرف كبير وحرف صغير.',
                'password.numbers' => 'كلمة المرور يجب أن تحتوي على رقم واحد على الأقل.',
                'password.symbols' => 'كلمة المرور يجب أن تحتوي على رمز خاص واحد على الأقل.',
            ];
        }
}
