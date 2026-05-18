<?php

namespace App\Http\Controllers\Application\Community;

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

    public function index(Post $post)
    {
        $comments = $this->commentService->getComments($post,perPage: 5) ;
        return $this->successApi($comments , 'Comments of post fetched successfully') ;
    }

    public function replies(Comment $comment)
    {
        $replies = $this->commentService->getReplies($comment,perPage: 5);
        return $this->successApi($replies , 'replies of Comment fetched successfully') ;
    }

    public function store(Request $request, Post $post)
    {
        $data = $request->validate([
            'body'      => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);
        $comment = $this->commentService->addComment($post,$request->user(),$data['body'],$data['parent_id'] ?? null);
        return $this->successApi($comment , 'Comment created successfully' , 201) ;
    }

    public function update(Request $request, Comment $comment)
    {
        $this->authorize('update', $comment);
        
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $this->commentService->updateComment($comment, $data['body']);
        return $this->successApi($comment , 'Comment updated successfully') ;
    }

    public function destroy(Request $request, Comment $comment)
    {
        $this->authorize('delete', $comment);
        $this->commentService->deleteComment($comment);
        return $this->successApi(null , 'Comment deleted successfully') ;
    }
}
