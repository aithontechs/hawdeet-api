<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use App\Services\Book\UserBookService;

class BookPolicy extends ModelPolicy
{
    public function __construct(private readonly UserBookService $userBookService) {}

    public function access(User $user, Book $book): bool
    {
        if (!$book->published) {
            return false;
        }
        if ($book->is_free) {
            return true;
        }

        if (in_array($book->id, $this->userBookService->getUserBookIds($user))) {
            return true;
        }

        if ($book->is_subscription_included && $this->userBookService->hasActiveSubscription($user)) {
            return true;
        }

        return false;
    }
}
