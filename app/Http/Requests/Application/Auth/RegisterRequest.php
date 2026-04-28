<?php

namespace App\Http\Requests\Application\Auth;

use Illuminate\Foundation\Http\FormRequest;

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
            'phone' => ['nullable', 'string','digits:11', 'unique:users,phone'],
            'birth_date' => ['nullable', 'date'],
            'password' => [
                'required_without:social_id',
                'string',
                'min:8',
                'max:25'
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
}
