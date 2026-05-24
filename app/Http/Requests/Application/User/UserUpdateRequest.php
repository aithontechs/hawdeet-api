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
            'phone' => [
                'sometimes',
                'digits:11',
                Rule::unique('users', 'phone')->Ignore(auth()->id()),
            ],
            'birth_date' => 'sometimes|date',
            'avatar_url' => 'sometimes|image',
            'bio' => 'sometimes|string|max:1000'
        ];
    }
}
