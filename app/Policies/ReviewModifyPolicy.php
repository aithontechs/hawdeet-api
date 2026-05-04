<?php

namespace App\Policies;

use App\Models\BookReview;
use App\Models\User;

class ReviewModifyPolicy
{
    public function modify(User $user, BookReview $review): bool
    {
        return $user->id === $review->user_id;
    }
}
