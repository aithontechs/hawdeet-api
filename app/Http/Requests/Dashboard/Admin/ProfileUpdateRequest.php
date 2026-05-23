<?php

namespace App\Http\Requests\Dashboard\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true ;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('admins', 'email')->ignore(auth('admin-api')->id())
            ],
            'phone' => [
                'sometimes',
                'string',
                Rule::unique('admins', 'phone')->ignore(auth('admin-api')->id())
            ],
            'avatar_url' => 'sometimes|image|max:2048' ,
            'new_password' => ['sometimes', 'confirmed', 'min:8' , 'max:25'],
        ];
    }
}
