<?php

namespace App\Http\Requests\Dashboard\Authorize;

use Illuminate\Foundation\Http\FormRequest;

class PermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        return [
            'permissions' => 'required|array|min:1',
            'permissions.*.name' => 'required|string|min:3|max:60' ,
            'permissions.*.permission' => 'required|string|min:3|max:70'
        ];
    }
}
