<?php

namespace App\Http\Controllers\Application\Category;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ResponseApi;

class CategoryController extends Controller
{
    use ResponseApi ;
    public function index()
    {
        $categories = Category::get();
        return $this->successApi($categories, 'Categories retrieved successfully');
    }
}
