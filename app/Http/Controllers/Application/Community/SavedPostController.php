<?php

namespace App\Http\Controllers\Application\Community;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Community\Save\SavedPostService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class SavedPostController extends Controller
{
    use ResponseApi ;
    public function __construct(
        private readonly SavedPostService $savedPostService
    ) {}

    public function index(Request $request)
    {
        $actor      = $request->user();
        $pagination = $request->query('pagination', 'cursor');
        $saved = $this->savedPostService->getSavedPosts($actor, $pagination);
        return $this->successApi($saved , 'Saved Posts fetched successfully') ;
    }


    public function toggle(Request $request, Post $post)
    {
        $actor  = $request->user();
        $result = $this->savedPostService->toggle($actor, $post);
        return $this->successApi($result['saved'] , $result['saved'] ? 'Post saved successfully' : 'Post unsaved successfully') ;
    }
}
