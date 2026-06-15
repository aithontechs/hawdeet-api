<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function __construct()
    {
        //
    }

    public function create(User | Admin $actor): bool
    {
        if ($actor instanceof User) {
            return ! is_null($actor->email_verified_at);
        }

        return true;
    }

    public function update(User | Admin $actor , Post $post)
    {
        return $post->isOwnedBy($actor);
    }

    public function delete(User | Admin $actor, Post $post)
    {
        if ($actor instanceof Admin) return true;
        return $post->isOwnedBy($actor);
    }

}
