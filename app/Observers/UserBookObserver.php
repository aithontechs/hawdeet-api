<?php

namespace App\Observers;

use App\Models\UserBook;
use App\Services\Book\UserBookService;
use App\Services\User\ProfileService;

class UserBookObserver
{
    public function __construct(private readonly UserBookService $userBookService ,private readonly ProfileService $profileService,) {}

    public function created(UserBook $userBook): void
    {
        $this->userBookService->clearCache($userBook->user_id);
        $this->profileService->clearCache($userBook->user_id);
    }

    public function deleted(UserBook $userBook): void
    {
        $this->userBookService->clearCache($userBook->user_id);
        $this->profileService->clearCache($userBook->user_id);
    }

    public function updated(UserBook $userBook): void
    {
        $this->userBookService->clearCache($userBook->user_id);
        $this->profileService->clearCache($userBook->user_id);
    }


}
