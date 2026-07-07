<?php

namespace App\Http\Requests\Dashboard\Setting;

use Illuminate\Foundation\Http\FormRequest;

class SettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        return [
            'settings'  => 'sometimes|array|min:1',
            'settings.platform_name' => 'sometimes|string|max:100',
            'settings.default_language' => 'sometimes|string|max:10',
            'settings.current_currency' => 'sometimes|string|max:10',
            'settings.max_failed_login_attempts' => 'sometimes|integer|min:2',
            'settings.temporary_ban_duration_minutes' => 'sometimes|integer|min:1',
            'settings.platform_logo' => 'sometimes|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
        ];
    }
}
