<?php

namespace App\Http\Requests\Dashboard\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'phone' => 'nullable|digits:11|unique:users,phone',
            'password' => $this->isMethod('post')
                ? 'required|min:8|max:25'
                : 'sometimes|min:8|max:25',
            'birth_date' => $this->isMethod('post') ? 'required|date' : 'sometimes|date',
            'is_author' => 'nullable|boolean',
        ];
    }
}
