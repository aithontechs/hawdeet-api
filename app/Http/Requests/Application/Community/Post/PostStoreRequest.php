<?php

namespace App\Http\Requests\Application\Community\Post;

use Illuminate\Foundation\Http\FormRequest;

class PostStoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        return [
            'body'  => ['nullable', 'string', 'max:5000', 'required_without:media'],
            'media' => ['nullable','file','required_without:body','max:3500','mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function mediaType(): ?string
    {
        if (!$this->hasFile('media')) return null;

        $mime = $this->file('media')->getMimeType();

        return str_starts_with($mime, 'video/') ? 'video' : 'image';
    }
}
