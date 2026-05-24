<?php

namespace App\Services\Book ;

use App\Models\{Book, BookReadingProgress, User};
use Illuminate\Support\Carbon;

class BookReadingProgressService
{

    public function updateProgress(Book $book, User $user, int $currentPage)
    {
        abort_unless($book->published, 403, 'Unauthorized access to this book.');
        $totalPages = $book->total_pages;

        abort_if(
            $currentPage < 1 || $currentPage > $totalPages,
            422,
            "Page {$currentPage} is out of range."
        );

        $percentage = round(($currentPage / $totalPages) * 100, 2);
        $isCompleted = $currentPage >= $totalPages;

        $progress = BookReadingProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'book_id' => $book->id,
            ],
            [
                'current_page' => $currentPage,
                'total_pages'  => $totalPages,
                'percentage'   => $percentage,
                'status'       => $isCompleted ? 'completed' : 'reading',
                'last_read_at' => Carbon::now(),
                'completed_at' => $isCompleted ? Carbon::now() : null,
            ]
        );

        return $progress;
    }

    public function getProgress(Book $book, User $user)
    {
        return BookReadingProgress::query()->where('user_id', $user->id)->where('book_id', $book->id)->firstorfail();
    }

    public function getUserLibrary(User $user , $status)
    {
        $query = BookReadingProgress::query()
                    ->with('book:id,title,total_pages,cover')
                    ->where('user_id', $user->id);

        match ($status) {
            'reading' => $query->where('status', 'reading'),
            'completed' => $query->where('status', 'completed'),
            'recent' => $query->latest(),
            default => $query->orderByDesc('last_read_at'),
        };
        return $query->get();
    }

}
