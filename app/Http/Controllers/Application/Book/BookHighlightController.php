<?php

namespace App\Http\Controllers\Application\Book;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hightlight\HightlightRequest;
use App\Models\{BookHighlight , Book};
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class BookHighlightController extends Controller
{

    use ResponseApi ;


    public function store(HightlightRequest $request, Book $book)
    {
        $this->authorize('access', $book);
        $validated = $request->validated() ;
        $highlight = BookHighlight::create([
            'user_id'       => auth('user-api')->user()->id ,
            'book_id'       => $book->id,
            'page_number'   => $validated['page_number'],
            'selected_text' => $validated['selected_text'],
            'color'         => $validated['color'] ?? '#FFFF00',
            'position_data' => $validated['position_data'] ?? null,
        ]);
        return $this->successApi($highlight , 'Hightlight created successfully' , 201) ;

    }

    public function destroy(Book $book, BookHighlight $highlight)
    {
        $this->authorize('access', $book);
        abort_if(
            $highlight->user_id !== auth('user-api')->user()->id ,
            403,
            'You do not own this highlight.'
        );

        $highlight->delete();
        return $this->successApi(null , 'Hightlight deleted successfully') ;
    }
}
