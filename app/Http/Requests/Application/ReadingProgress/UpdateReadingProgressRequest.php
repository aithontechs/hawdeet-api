<?php

namespace App\Http\Requests\Application\ReadingProgress;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        return [
            'current_page' => ['required', 'integer', 'min:1'],
        ];
    }
}
