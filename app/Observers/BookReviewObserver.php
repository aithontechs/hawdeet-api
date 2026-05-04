<?php

namespace App\Observers;

use App\Models\BookReview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BookReviewObserver
{
    public function created(BookReview $review): void
    {
        $this->recalculate($review->book_id);
    }

    public function updated(BookReview $review): void
    {
        if ($review->wasChanged('rating')) {
            $this->recalculate($review->book_id);
        }
    }

    public function deleted(BookReview $review): void
    {
        $this->recalculate($review->book_id);
    }


    private function recalculate(int $bookId): void
    {
        $stats = DB::table('book_reviews')->where('book_id', $bookId)->where('is_approve', true)
                                        ->selectRaw('COUNT(*) as total, COALESCE(AVG(rating), 0) as avg')
                                        ->first();

        DB::table('books')->where('id', $bookId)
                ->update([
                    'avg_rating'    => round($stats->avg, 2),
                    'reviews_count' => $stats->total,
                ]);

        Cache::forget("book_stats:{$bookId}");
    }
}
