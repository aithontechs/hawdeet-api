<?php

namespace App\Http\Controllers\Application\Book;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\Book\BookReaderService;
use App\Services\Book\UserBookService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class BookReaderController extends Controller
{
    use ResponseApi ;
    public function __construct(private readonly BookReaderService $readerService , private readonly UserBookService $userBookService)
    {

    }


    public function index(Book $book)
    {
        $this->authorize('access', $book);
        $info = $this->readerService->getReadingSessionInfo($book, auth('user-api')->user());
        return $this->successApi($info , 'Reading Session fetched successfully') ;

    }

    public function page(Book $book, int $page)
    {
        $this->authorize('access', $book);

        abort_if(
            $page < 1 || $page > $book->total_pages,
            422,
            "Page {$page} is out of range. Book has {$book->total_pages} pages."
        );

        $user = auth('user-api')->user();

        if ($book->is_subscription_included && !$book->is_free) {
            $isPurchased = in_array($book->id, $this->userBookService->getUserBookIds($user));

            if (!$isPurchased && $this->userBookService->hasActiveSubscription($user)) {
                $this->userBookService->recordSubscriptionAccess($user, $book);
            }
        }

        return $this->readerService->streamPage($book, $page, $user);
    }


    public function preview(Book $book, int $page)
    {
        $user = auth('user-api')->user();
        return $this->readerService->streamPreview($book, $page ,$user);
    }

}
