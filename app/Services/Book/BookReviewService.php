<?php

namespace App\Services\Book ;

use App\Models\{BookReview , Book};
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class BookReviewService
{
    public function __construct(private readonly UserBookService $userBookService)
    {

    }

    public function getBookReviews(Book $book, int $perPage = 15)
    {
        return BookReview::query()->forBook($book->id)->approve()->with('user:id,name,avatar_url')->latest()->paginate($perPage);
    }

    public function store(Book $book, User $user, array $validated)
    {
        $hasPurchased = in_array($book->id,$this->userBookService->getUserBookIds($user));
        abort_unless($hasPurchased, 403, 'You must access this book across purchase or subscription before reviewing it.') ;
        abort_if($book->hasReviewBy($user->id),422,'You have already reviewed this book.');
        $validated['user_id'] = $user->id;
        $validated['book_id'] = $book->id;
        return BookReview::create($validated) ;
    }


    public function update(BookReview $review, array $validted)
    {
        $review->update([
            'rating'  => $validted['rating']  ?? $review->rating,
            'comment' => $validted['comment'] ?? $review->comment,
        ]);

        return $review->fresh();
    }

    public function delete(BookReview $review): void
    {
        $review->delete();
    }

    public function getStats(Book $book): array
    {
        return Cache::remember("book_stats:{$book->id}", 3600 , function () use ($book) {

                $stats = BookReview::query()->forBook($book->id)->approve()
                            ->selectRaw('
                                COUNT(*) as total,
                                ROUND(AVG(rating), 2) as average,
                                SUM(rating = 5) as five_star,
                                SUM(rating = 4) as four_star,
                                SUM(rating = 3) as three_star,
                                SUM(rating = 2) as two_star,
                                SUM(rating = 1) as one_star
                            ')
                            ->first();

                $total = $stats->total ?: 1;

                return [
                    'average'   => (float) $stats->average,
                    'total'     => (int)   $stats->total,
                    'breakdown' => [
                        5 => ['count' => (int) $stats->five_star,  'percentage' => round($stats->five_star  / $total * 100)],
                        4 => ['count' => (int) $stats->four_star,  'percentage' => round($stats->four_star  / $total * 100)],
                        3 => ['count' => (int) $stats->three_star, 'percentage' => round($stats->three_star / $total * 100)],
                        2 => ['count' => (int) $stats->two_star,   'percentage' => round($stats->two_star   / $total * 100)],
                        1 => ['count' => (int) $stats->one_star,   'percentage' => round($stats->one_star   / $total * 100)],
                    ],
                ];
            }
        );
    }

}
