<?php

namespace App\Http\Requests\Hightlight;

use Illuminate\Foundation\Http\FormRequest;

class HightlightRequest extends FormRequest
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
            'page_number'          => ['required', 'integer', 'min:1'],
            'selected_text'        => ['required', 'string', 'max:2000'],
            'color'                => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position_data'        => ['required', 'array'],
            'position_data.x'      => ['required', 'numeric', 'min:0', 'max:1'],
            'position_data.y'      => ['required', 'numeric', 'min:0', 'max:1'],
            'position_data.width'  => ['required', 'numeric', 'min:0', 'max:1'],
            'position_data.height' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
