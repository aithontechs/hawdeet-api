<?php
namespace App\Http\Requests\Dashboard\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category?->id;

        return [
            'name'      => 'required|string|max:255',
            'parent_id' => [
                'nullable',
                'exists:categories,id',

                function ($attribute, $value, $fail) use ($categoryId) {
                    if ($value && $value == $categoryId) {
                        $fail('Category cannot be its own parent.');
                    }
                },

                function ($attribute, $value, $fail) use ($category) {
                    if ($value && $category) {
                        $newParent = Category::find($value);
                        if ($newParent?->isDescendantOf($category->id)) {
                            $fail('This assignment would create a circular loop in categories.');
                        }
                    }
                },
            ],
        ];
    }
}
