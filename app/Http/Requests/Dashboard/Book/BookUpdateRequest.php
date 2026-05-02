<?php

namespace App\Http\Requests\Dashboard\Book;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bookId = $this->route('book') instanceof \App\Models\Book
                    ? $this->route('book')->id
                    : $this->route('book');

        return [
            'title'          => ['sometimes', 'string', 'max:255'],
            'slug'           => ['sometimes', 'string', 'max:255', Rule::unique('books', 'slug')->ignore($bookId)],
            'description'    => ['sometimes', 'string'],
            'covre'          => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'file'           => ['sometimes', 'file', 'mimes:pdf', 'max:307200'],
            'price'          => ['sometimes', 'numeric', 'min:0'],
            'age_min'        => ['sometimes', 'integer', 'min:0'],
            'total_pages'    => ['nullable', 'integer', 'min:0'],
            'is_free'        => ['boolean'],
            'author_id'  => ['sometimes' , 'exists:users,id'],
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'preview_start_page' => ['required_with:file' , 'numeric' , 'min:0'],
            'preview_end_page' => ['required_with:file' , 'numeric' , 'min:1'],
            'is_subscription_included' => ['nullable' , 'boolean']
        ];
    }

}
