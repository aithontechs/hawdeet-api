<?php

namespace App\Http\Controllers\Application\Book;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Book\ReviewStoreRequest;
use App\Http\Requests\Application\Book\ReviewUpdateRequest;
use App\Models\Book;
use App\Models\BookReview;
use App\Services\Book\BookReviewService;
use App\Traits\ResponseApi;

class BookReviewController extends Controller
{
    use ResponseApi;

    public function __construct(private readonly BookReviewService $reviewService) {}

    public function index(Book $book)
    {
        $reviews = $this->reviewService->getBookReviews($book);
        $stats   = $this->reviewService->getStats($book);

        return $this->successApi([
            'stats'   => $stats,
            'reviews' => $reviews,
        ], 'Reviews fetched successfully');
    }


    public function store(ReviewStoreRequest $request, Book $book)
    {
        $review = $this->reviewService->store($book,auth('user-api')->user(),$request->validated());
        return $this->successApi($review, 'Review submitted successfully', 201);
    }

    public function update(ReviewUpdateRequest $request, Book $book, BookReview $review)
    {
        $this->authorize('modify', $review);
        $updated = $this->reviewService->update($review, $request->validated());
        return $this->successApi($updated, 'Review updated successfully');
    }

    public function destroy(Book $book, BookReview $review)
    {
        $this->authorize('modify', $review);
        $this->reviewService->delete($review);
        return $this->successApi(null, 'Review deleted successfully');
    }
}
