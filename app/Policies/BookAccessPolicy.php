<?php

namespace App\Policies;

use App\Models\{User , Book} ;
use App\Services\Book\UserBookService;

class BookAccessPolicy
{

    public function __construct(private readonly UserBookService $userBookService) {}

    public function access(User $user, Book $book)
    {
        if ($book->is_free) {
            return true;
        }

        $bookIds = $this->userBookService->getUserBookIds($user);
        return in_array($book->id, $bookIds);
    }
}
