<?php

namespace App\Http\Requests\Dashboard\Authorize;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:40'],
            'permissions' => 'required|array|min:1' ,
            'permissions.*' => 'required|exists:permissions,id',
        ];
    }
}
