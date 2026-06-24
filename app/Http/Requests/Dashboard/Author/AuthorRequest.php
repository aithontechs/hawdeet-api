<?php

namespace App\Http\Requests\Dashboard\Author;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthorRequest extends FormRequest
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
            'birth_date' => $this->isMethod('post') ? 'required|date' : 'sometimes|date',
        ];
    }
}
