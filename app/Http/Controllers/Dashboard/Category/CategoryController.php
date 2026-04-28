<?php
namespace App\Http\Controllers\Dashboard\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Category\CategoryRequest;
use App\Models\Category;
use App\Traits\ResponseApi;

class CategoryController extends Controller
{
    use ResponseApi ;

    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index()
    {
        $categories = Category::with('childrenRecursive')
            ->whereNull('parent_id')
            ->latest()
            ->paginate(15);

        return $this->successApi($categories, 'Categories fetched successfully');
    }

    public function store(CategoryRequest $request)
    {
        $category = Category::create($request->validated());
        return $this->successApi($category, 'Category Created successfully', 201);
    }

    public function show(Category $category)
    {
        $category->load('childrenRecursive', 'parent');
        return $this->successApi($category, 'Category fetched successfully');
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated());
        return $this->successApi($category, 'Category updated successfully');
    }

    public function destroy(Category $category)
    {
        if ($category->children()->exists()) {
            return $this->errorApi('Cannot delete category with subcategories.', 422);
        }

        $category->delete();
        return $this->successApi(null, 'Category deleted successfully');
    }
}
