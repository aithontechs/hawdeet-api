<?php

namespace App\Observers;

use App\Models\UserBook;
use App\Services\Book\UserBookService;

class UserBookObserver
{
    public function __construct(private readonly UserBookService $userBookService) {}

    public function created(UserBook $userBook): void
    {
        $this->userBookService->clearCache($userBook->user_id);
    }

    public function deleted(UserBook $userBook): void
    {
        $this->userBookService->clearCache($userBook->user_id);
    }

    public function updated(UserBook $userBook): void
    {
        $this->userBookService->clearCache($userBook->user_id);
    }


}
