<?php

namespace App\Http\Requests\Dashboard\Book;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookStoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['required', 'string'],
            'cover'          => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2120'],
            'file'           => ['required', 'file', 'mimes:pdf', 'max:30720 '],  // 30 MB
            'price'          => ['required', 'numeric', 'min:0'],
            'compare_price'  => ['nullable', 'numeric', 'min:0' , 'gt:price'],
            'age_min'        => ['required', 'integer', 'min:0'],
            // 'total_pages'    => ['nullable', 'integer', 'min:0'],
            'is_free'        => ['boolean'],
            'published'      => ['boolean'],
            'author_id'  => ['required' , 'exists:users,id'],
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'preview_start_page' => ['required' , 'numeric' , 'min:0'],
            'preview_end_page' => ['required' , 'numeric' , 'min:1'],
            'is_subscription_included' => ['nullable' , 'boolean']
        ];
    }

}


