<?php

namespace App\Http\Controllers\Application\Book;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Category;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class BookController extends Controller
{
    use ResponseApi ;

    public function index(Request $request)
    {
        $books = Book::search($request)->where('published' , true)->latest()->paginate(15);
        return $this->successApi($books , 'Books fetched successfully') ;
    }


    public function booksByCategory(Category $category)
    {
        $categoryIds = $this->getCategoryWithChildrenIds($category);

        $books = Book::where('published', true)
            ->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            })
            ->paginate(15);

        return $this->successApi($books, 'Books fetched by category with children');
    }




    public function getCategoryWithChildrenIds(Category $category)
    {
        $ids = [$category->id];

        $children = $category->childrenRecursive;

        $flatten = function ($cats) use (&$flatten, &$ids) {
            foreach ($cats as $cat) {
                $ids[] = $cat->id;
                if ($cat->childrenRecursive) {
                    $flatten($cat->childrenRecursive);
                }
            }
        };

        $flatten($children);

        return $ids;
    }
}
