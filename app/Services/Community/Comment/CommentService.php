<?php

namespace App\Services\Community\Comment ;

use App\Models\{Admin, Comment, Post, User};

class CommentService
{

    public function addComment(Post $post, User|Admin $actor, string $body, ?int $parentId = null): Comment
    {
        abort_unless($post->isVisible(), 403, 'Cannot comment on this post.');

        $depth = 0;

        if ($parentId) {
            $parent = Comment::findOrFail($parentId);
            abort_unless($parent->canReply(), 422, 'Maximum comment depth reached.');
            abort_unless($parent->post_id === $post->id, 422, 'Comment does not belong to this post.');
            $depth = $parent->depth + 1;
        }

        $comment = Comment::create([
            'post_id'          => $post->id,
            'parent_id'        => $parentId,
            'commentable_id'   => $actor->id,
            'commentable_type' => get_class($actor),
            'body'             => $body,
            'depth'            => $depth,
        ]);

        $post->increment('comments_count');
        if ($parentId) {
            Comment::where('id', $parentId)->increment('replies_count');
        }

        return $comment->load('commentable:id,name,avatar_url');
    }

    public function updateComment(Comment $comment, string $body): Comment
    {
        $comment->update(['body' => $body]);
        return $comment->fresh()->load('commentable:id,name,avatar_url');
    }

    public function deleteComment(Comment $comment): void
    {
        $comment->post->decrement('comments_count');

        if ($comment->parent_id) {
            Comment::where('id', $comment->parent_id)->decrement('replies_count');
        }

        $comment->delete();
    }

    public function getComments(Post $post, int $perPage = 5)
    {
        return Comment::query()
                    ->with([
                        'commentable:id,name,avatar_url',
                        'latestReplies.commentable:id,name,avatar_url',
                    ])
                    ->where('post_id', $post->id)
                    ->whereNull('parent_id')
                    ->orderByDesc('created_at')
                    ->cursorPaginate($perPage);
    }

    public function getReplies(Comment $comment, int $perPage = 5)
    {
        return Comment::query()
            ->with('commentable:id,name,avatar_url')
            ->where('parent_id', $comment->id)
            ->orderBy('created_at')
            ->cursorPaginate($perPage);
    }
}
