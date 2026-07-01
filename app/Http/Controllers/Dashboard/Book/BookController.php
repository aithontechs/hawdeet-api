<?php

namespace App\Http\Controllers\Dashboard\Book;

use App\Exports\BooksExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Book\BookStoreRequest;
use App\Http\Requests\Dashboard\Book\BookUpdateRequest;
use App\Models\Book;
use App\Services\Book\BookService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;


class BookController extends Controller
{
    use ResponseApi ;

    public function __construct(private readonly BookService $bookService) {
        $this->authorizeResource(Book::class, 'book');
    }


    public function index(Request $request)
    {
        $books = Book::query()->withRelations()->filter($request)->latest()
                        ->paginate($request->integer('per_page', 15));
        return $this->successApi($books , 'Books fetched successfully') ;
    }

    public function show(Book $book)
    {
        $book->load(['categories', 'uploader:id,name' , 'author:id,name']);
        return $this->successApi(array_merge($book->toArray(), ['cover_url' => $book->cover_url,]) , 'The book fetch successfully');
    }

    public function store(BookStoreRequest $request)
    {
        $book = $this->bookService->create($request->validated(),$request->file('cover'),$request->file('file'));
        return $this->successApi($book,$book->file ? 'Book created. File is being processed.' : 'Book created successfully.',201);
    }


    public function update(BookUpdateRequest $request, Book $book)
    {
        $book = $this->bookService->update($book,$request->validated(),$request->file('cover'),$request->file('file'));
        $message = $request->hasFile('file')? 'Book updated. File is being processed.': 'Book updated successfully.';
        return $this->successApi($book, $message);
    }


    public function publish(Book $book)
    {
        $this->authorize('publish', $book) ;
        if ($book->published) {
            return $this->errorApi('Book is already published.', 422);
        }
        $book = $this->bookService->publish($book);
        return $this->successApi($book, 'Book published successfully.');
    }

    public function unpublish(Book $book)
    {
        $this->authorize('unpublish', $book) ;

        if (! $book->published) {
            return $this->errorApi('Book is already unpublished.', 422);
        }
        $book = $this->bookService->unpublish($book);
        return $this->successApi($book, 'Book unpublished successfully.');
    }

    public function destroy(Book $book)
    {
        $this->bookService->delete($book);
        return $this->successApi(null, 'Book deleted successfully.');
    }

    public function streamFull(Book $book): StreamedResponse
    {
        $this->authorize('streamfull', $book) ;
        abort_unless($book->isDigital(), 403, 'This book has no digital version.');
        return $this->bookService->streamBook($book);
    }

    public function streamPreview(Book $book): StreamedResponse
    {
        $this->authorize('streampreview', $book) ;
        abort_unless($book->isDigital(), 403, 'This book has no digital version.');
        return $this->bookService->streamPreview($book);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Book::class ) ;
        $fileName = 'books_' . now()->format('Y_m_d_His') . '.xlsx';
        return Excel::download(new BooksExport($request), $fileName);
    }

    public function stats()
    {
        $this->authorize('viewAny', Book::class ) ;
        $stats = $this->bookService->getStats();
        return $this->successApi($stats, 'Books statistics fetched successfully.');
    }



}
