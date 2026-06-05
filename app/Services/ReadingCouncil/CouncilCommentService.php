<?php

namespace App\Services\ReadingCouncil;

use App\Models\{CouncilComment, ReadingCouncil, User};

class CouncilCommentService
{
    public function getComments(ReadingCouncil $council, int $perPage = 10)
    {
        return CouncilComment::query()
            ->with([
                'user:id,name,avatar_url',
                'latestReplies.user:id,name,avatar_url',
            ])
            ->where('reading_council_id', $council->id)
            ->whereNull('parent_id')
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);
    }

    public function addComment(
        ReadingCouncil $council,
        User $user,
        string $body,
        ?int $parentId = null
    ): CouncilComment {

        abort_unless($council->isMember($user), 403, 'You must join the council first.');
        abort_if($council->isClosed(), 403, 'This council is closed.');

        $depth = 0;

        if ($parentId) {
            $parent = CouncilComment::findOrFail($parentId);

            abort_unless(
                $parent->reading_council_id === $council->id,
                422,
                'Comment does not belong to this council.'
            );

            abort_unless($parent->canReply(), 422, 'Maximum reply depth reached.');
            $depth = $parent->depth + 1;
        }

        $comment = CouncilComment::create([
            'reading_council_id' => $council->id,
            'user_id'            => $user->id,
            'parent_id'          => $parentId,
            'body'               => $body,
            'depth'              => $depth,
        ]);

        $council->increment('comments_count');

        if ($parentId) {
            CouncilComment::where('id', $parentId)->increment('replies_count');
        }

        return $comment->load('user:id,name,avatar_url');
    }

    public function updateComment(
        CouncilComment $comment,
        User $user,
        string $body
    ): CouncilComment {
        abort_unless($comment->isOwnedBy($user), 403, 'Not your comment.');
        $comment->update(['body' => $body]);
        return $comment->fresh()->load('user:id,name,avatar_url');
    }

    public function deleteComment(CouncilComment $comment, User $user): void
    {
        abort_unless($comment->isOwnedBy($user), 403, 'Not your comment.');

        $comment->council()->decrement('comments_count');

        if ($comment->parent_id) {
            CouncilComment::where('id', $comment->parent_id)->decrement('replies_count');
        }

        $comment->delete();
    }

    public function getReplies(CouncilComment $comment, int $perPage = 10)
    {
        return CouncilComment::query()
            ->with('user:id,name,avatar_url')
            ->where('parent_id', $comment->id)
            ->orderBy('created_at')
            ->cursorPaginate($perPage);
    }
}
