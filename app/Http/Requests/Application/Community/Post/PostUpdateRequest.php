<?php

namespace App\Http\Requests\Application\Community\Post;

use Illuminate\Foundation\Http\FormRequest;

class PostUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        return [
            'body'         => ['nullable', 'string', 'max:5000'],
            'media'        => [
                'nullable', 'file', 'max:102400',
                'mimes:jpg,jpeg,png,webp,mp4,mov,avi',
            ],
            'remove_media' => ['nullable', 'boolean'],
        ];
    }
}
