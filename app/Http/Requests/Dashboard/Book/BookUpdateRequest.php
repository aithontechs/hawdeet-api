<?php

namespace App\Http\Requests\Dashboard\Book;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BookUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $book   = $this->route('book');
        $bookId = $book instanceof \App\Models\Book ? $book->id : $book;
        $type   = $this->input('type', $book instanceof \App\Models\Book ? $book->type : 'digital');

        $isDigital  = in_array($type, ['digital', 'both']);
        $isPhysical = in_array($type, ['physical', 'both']);

        return [
            'title'       => ['sometimes', 'string', 'max:255'],
            'slug'        => ['sometimes', 'string', 'max:255', Rule::unique('books', 'slug')->ignore($bookId)],
            'description' => ['sometimes', 'string'],
            'type'        => ['sometimes', 'in:digital,physical,both'],
            'age_min'     => ['sometimes', 'integer', 'min:0'],
            'is_free'     => ['boolean'],
            'is_subscription_included' => ['nullable', 'boolean'],
            'author_id'      => ['sometimes', 'exists:users,id'],
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],

            'cover' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'file'  => [
                $this->shouldRequireFile($book, $type) ? 'required' : 'sometimes',
                'file', 'mimes:pdf', 'max:20480' ,  'mimetypes:application/pdf'
            ],
            'preview_start_page' => ['required_with:file,preview_end_page', 'integer', 'min:1'],
            'preview_end_page'   => ['required_with:file,preview_start_page', 'integer', 'min:1', 'gte:preview_start_page'],

            'price'         => [$isDigital ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0', 'gt:price'],

            'physical_price'         => [$isPhysical ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
            'physical_compare_price' => ['nullable', 'numeric', 'min:0', 'gt:physical_price'],
            'physical_stock'         => [$isPhysical ? 'sometimes' : 'nullable', 'integer', 'min:1'],
        ];
    }


    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $book       = $this->route('book');
            $type       = $this->input('type', $book instanceof \App\Models\Book ? $book->type : 'digital');

            $hasPreviewRangeInput = $this->filled('preview_start_page') || $this->filled('preview_end_page');

            $isDigital  = in_array($type, ['digital', 'both']);
            $isPhysical = in_array($type, ['physical', 'both']);

            $isChangingType = $this->has('type') && $book instanceof \App\Models\Book && $book->type !== $type;
            $isCreatingOrNewPhysical = !($book instanceof \App\Models\Book);

                if ($type === 'physical' && $hasPreviewRangeInput) {
                $validator->errors()->add('preview_start_page', 'Cannot set preview pages for physical-only books.');
            }

            if ($hasPreviewRangeInput && !$this->hasFile('file')&& $book instanceof \App\Models\Book && !$book->file) {
                $validator->errors()->add('preview_start_page', 'Cannot set preview pages without an uploaded book file.');
            }

            if ($hasPreviewRangeInput && !$this->hasFile('file')&& $book instanceof \App\Models\Book && $book->published && in_array($book->type, ['digital', 'both'])) {
                $validator->errors()->add('preview_start_page', 'Cannot change preview of a published digital book.');
            }

            if ($isChangingType && $isDigital && !$book->file && !$this->hasFile('file')) {
                $validator->errors()->add('file', 'Book file is required when changing type to digital or both.');
            }

            if ($isDigital && !$this->filled('price') && $isChangingType && !$book->price) {
                $validator->errors()->add('price', 'Digital price is required when type is digital or both.');
            }

            if ($isPhysical && $isChangingType && !$this->filled('physical_price') && !$book->physical_price) {
                $validator->errors()->add('physical_price', 'Physical price is required when type is physical or both.');
            }

            if ($isPhysical
                && ($isChangingType || $isCreatingOrNewPhysical)
                && !$this->filled('physical_stock')
                && !($book instanceof \App\Models\Book && $book->physical_stock)
            ) {
                $validator->errors()->add('physical_stock', 'Physical stock is required when type is physical or both.');
            }

            if ($type === 'physical' && $this->hasFile('file')) {
                $validator->errors()->add('file', 'Cannot upload a book file for physical-only books.');
            }
        });
    }


    private function shouldRequireFile($book, string $type): bool
    {
        $isDigital      = in_array($type, ['digital', 'both']);
        $isChangingType = $this->has('type') && $book instanceof \App\Models\Book && $book->type !== $type;
        $hasOldFile     = $book instanceof \App\Models\Book && $book->file;

        return $isDigital && $isChangingType && !$hasOldFile;
    }
}
