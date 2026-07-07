<?php

namespace App\Http\Requests\Dashboard\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminStoreRequest extends FormRequest
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
            'name' => ['required','string','max:255'],
            'email' => ['required','email','unique:admins,email'],
            'phone' => ['required','regex:/^01[0125][0-9]{8}$/', 'unique:admins,phone'],
            'password' => ['required','string','min:9' , 'confirmed'],
            'role_id' => ['required','exists:roles,id'],
            'avatar_url' => ['nullable','image','max:2048'],
        ];
    }
}
