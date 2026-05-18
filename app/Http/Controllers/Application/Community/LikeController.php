<?php

namespace App\Http\Controllers\Application\Community;

use App\Http\Controllers\Controller;
use App\Models\{Comment, Post};
use App\Services\Community\Like\LikeService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    use ResponseApi ;

    public function __construct(private readonly LikeService $likeService) {}

    public function likePost(Request $request, Post $post)
    {
        $result = $this->likeService->toggle($request->user(), $post);
        return $this->successApi($result , 'Excution operation on like successfully') ;
    }

    // POST /api/comments/{comment}/like
    public function likeComment(Request $request, Comment $comment)
    {
        $result = $this->likeService->toggle($request->user(), $comment);
        return $this->successApi($result , 'Excution operation on like successfully') ;
    }
}
