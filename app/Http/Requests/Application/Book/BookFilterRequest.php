<?php

namespace App\Http\Requests\Application\Book;

use Illuminate\Foundation\Http\FormRequest;

class BookFilterRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true ;
    }

    public function rules(): array
    {
        return [
            'search'      => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'author_id'   => ['nullable', 'integer', 'exists:users,id'],
            'language'    => ['nullable', 'string', 'max:10'],
            'price_min'   => ['nullable', 'numeric', 'min:0'],
            'price_max'   => ['nullable', 'numeric', 'min:0'],
            'rating_min'  => ['nullable', 'numeric', 'min:0', 'max:5'],
            'sort'        => ['nullable', 'in:latest,price_asc,price_desc,rating'],
            'type'        => ['nullable', 'in:digital,physical,both'],
        ];
    }
}
