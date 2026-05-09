<?php

namespace App\Http\Controllers\Application\Book;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\ReadingProgress\UpdateReadingProgressRequest;
use App\Models\Book;
use App\Services\Book\BookReadingProgressService;
use App\Traits\ResponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookReadingProgressController extends Controller
{
    use ResponseApi ;
    public function __construct(
        private readonly BookReadingProgressService $progressService
    ) {}

    public function update(UpdateReadingProgressRequest $request, Book $book): JsonResponse
    {
        $this->authorize("access", $book);
        $progress = $this->progressService->updateProgress(
            $book,
            $request->user(),
            $request->integer('current_page')
        );

        return $this->successApi([
                'current_page' => $progress->current_page,
                'total_pages'  => $progress->total_pages,
                'percentage'   => $progress->percentage,
                'status'       => $progress->status,
                'last_read_at' => $progress->last_read_at,
                'completed_at' => $progress->completed_at,
            ] ,'Updated Progress successfully') ;
    }

    public function show(Request $request, Book $book)
    {
        $this->authorize("access", $book);
        $progress = $this->progressService->getProgress($book, $request->user());
        abort_if(is_null($progress), 404, 'No reading progress found for this book.');
        return $this->successApi([
            'current_page' => $progress->current_page,
            'total_pages'  => $progress->total_pages,
            'percentage'   => $progress->percentage,
            'status'       => $progress->status,
            'last_read_at' => $progress->last_read_at,
            'completed_at' => $progress->completed_at,
        ] , 'Reading Progress of book fetched successfully');
    }

}
