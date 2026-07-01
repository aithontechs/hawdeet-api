<?php

namespace App\Policies;

use App\Models\{Admin, Comment, User};

class CommentPolicy
{
    public function update(User|Admin $actor, Comment $comment): bool
    {
        return $comment->isOwnedBy($actor);
    }

    public function delete(User|Admin $actor, Comment $comment): bool
    {
        if($actor instanceof Admin) return true ;
        return $comment->isOwnedBy($actor);
    }
}
