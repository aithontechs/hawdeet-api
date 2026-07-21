<?php

namespace App\Http\Requests\Application\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Propaganistas\LaravelPhone\Rules\Phone;

class RegisterRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true ;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', (new Phone())->international(), 'unique:users,phone'],
            'birth_date' => ['nullable', 'date' , 'before_or_equal:' . now()->subYears(8)->toDateString(), ],
            'password' => [
                'required_without:social_id',
                'string',
                'max:50',
                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'avatar_url' => [
                'nullable',
                'image',
                'max:2048'
            ],
            'social_provider' => ['nullable', 'string', 'in:google,facebook'],
            'social_id' => ['nullable', 'string', 'required_with:social_provider'],
        ];
    }

    public function messages()
    {
        return [
            'phone.phone' => 'رقم الهاتف غير صحيح. أدخل الرقم بصيغة دولية تبدأ بـ + وكود الدولة (مثال: +201012345678).',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.unique' => 'رقم الهاتف مستخدم من قبل.',
            'birth_date.before_or_equal' => 'يجب ألا يقل العمر عن 8 سنوات.',
            'password.min' => 'كلمة المرور يجب أن تحتوي على 12 أحرف على الأقل.',
            'password.max' => 'كلمة المرور يجب ألا تزيد عن 50 حرفًا.',
            'password.mixed' => 'كلمة المرور يجب أن تحتوي على حرف كبير وحرف صغير.',
            'password.numbers' => 'كلمة المرور يجب أن تحتوي على رقم واحد على الأقل.',
            'password.symbols' => 'كلمة المرور يجب أن تحتوي على رمز خاص واحد على الأقل.',
        ];
    }
}
