<?php

namespace App\Http\Controllers\Application\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Community\Post\{PostStoreRequest , PostUpdateRequest};
use App\Models\Post;
use App\Services\Community\Post\PostService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use ResponseApi ;

    public function __construct(private readonly PostService $postService) {}

    public function index(Request $request)
    {
        $type  = $request->query('pagination', 'cursor');
        $posts = $this->postService->getPosts($type);
        return $this->successApi($posts , 'post fetched successfully') ;
    }

    public function store(PostStoreRequest $request)
    {
        $this->authorize('create', Post::class) ;
        $post = $this->postService->createPost($request->user(),$request->validated(),$request->file('media')) ;
        return $this->successApi($post ,'Post created successfully' , 201);
    }

    public function show(Post $post)
    {
        return $this->successApi($post ,'Post Fetched successfully') ;
    }

    public function update(PostUpdateRequest $request, Post $post)
    {
        $this->authorize('update', $post);
        $post = $this->postService->updatePost($post, $request->validated() , $request->file('media'));
        return $this->successApi($post ,'Post updated successfully');
    }

    public function destroy(Request $request, Post $post)
    {
        $this->authorize('delete', $post);
        $this->postService->deletePost($post);
        return $this->successApi(null ,'Post deleted successfully') ;
    }
}
