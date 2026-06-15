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

    public function create(User | Admin $actor)
    {
        //
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
