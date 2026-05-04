<?php

namespace App\Http\Controllers\Application\Book;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\Book\BookReaderService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class BookReaderController extends Controller
{
    use ResponseApi ;
    public function __construct(private readonly BookReaderService $readerService) {

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

        return $this->readerService->streamPage($book, $page, auth('user-api')->user());
    }


    public function preview(Book $book, int $page)
    {
        $user = auth('user-api')->user();
        return $this->readerService->streamPreview($book, $page ,$user);
    }

}
