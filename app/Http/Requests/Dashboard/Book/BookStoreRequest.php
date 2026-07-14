<?php

namespace App\Http\Requests\Dashboard\Book;

use Carbon\Carbon;
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
        $type = $this->input('type', 'digital');
        $isDigital = in_array($type, ['digital', 'both']);
        $isPhysical = in_array($type, ['physical']);

        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'type'        => ['required', 'in:digital,physical,both'],
            'cover'       => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2120'],
            'author_id'   => ['required', 'exists:users,id'],
            'age_min'     => ['required', 'integer', 'min:0'],
            'is_free'     => ['boolean'],
            'is_subscription_included' => ['nullable', 'boolean'],
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],

            'file'   => [$isDigital ? 'required' : 'nullable', 'file', 'mimes:pdf' ,'mimetypes:application/pdf', 'max:20480'],
            'price'  => [$isDigital ? 'required' : 'nullable', 'numeric', 'min:0'],
            'compare_price'  => ['nullable', 'numeric', 'gt:price'],
            'preview_start_page'=> [$isDigital ? 'required' : 'nullable', 'integer', 'min:1'],
            'preview_end_page'  => [$isDigital ? 'required' : 'nullable', 'integer', 'min:2', 'gte:preview_start_page'],

            'physical_price'  => [$isPhysical ? 'required' : 'nullable', 'numeric', 'min:0'],
            'physical_compare_price'  => ['nullable', 'numeric', 'gt:physical_price'],
            'physical_stock'=> [$isPhysical ? 'required' : 'nullable', 'integer', 'min:1'],
            'total_pages'   => [$isPhysical ? 'required' : 'nullable', 'integer', 'min:1'],
            'size_book' => ['required' , 'string' , 'min:2' , 'max:10'],
            'release_year' => ['required' , 'integer' , 'digits:4' , 'min:1900' , 'max:' . Carbon::now()->addYear()->year],

            'physical_hard_cover_price'         => ['nullable', 'numeric', 'min:0'],
            'physical_hard_cover_compare_price' => ['nullable', 'numeric', 'gt:physical_hard_cover_price'],
            'physical_hard_cover_stock'         => ['nullable', 'integer', 'min:0'],
        ];
    }

}


