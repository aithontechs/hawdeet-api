<?php

namespace App\Http\Controllers\Dashboard\Comment;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Services\Community\Comment\CommentService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use ResponseApi ;

        public function __construct(
        private readonly CommentService $commentService
    ) {}

    public function index()
    {
        $comments = $this->commentService->getCommentsDashboard();
        return $this->successApi($comments , 'Comments fetched successfully') ;
    }

    public function destroy(Request $request, Comment $comment)
    {
        $this->authorize('delete', $comment);
        $this->commentService->deleteComment($comment);
        return $this->successApi(null , 'Comment deleted successfully') ;
    }


}
