<?php

namespace App\Policies;

use App\Models\{Admin, Comment, User};

class CommentPolicy
{
    public function viewAny(User|Admin $actor): bool
    {
        if($actor instanceof Admin){
            return $actor->hasPermission('comment.view') ;
        }
        return true ;
    }

    public function update(User|Admin $actor, Comment $comment): bool
    {
        return $comment->isOwnedBy($actor);
    }

    public function delete(User|Admin $actor, Comment $comment): bool
    {
        if($actor instanceof Admin){
            if($comment->isOwnedBy($actor)){
                return true ;
            }
            return $actor->hasPermission('comment.delete') ;
        }
        return $comment->isOwnedBy($actor);
    }
}
