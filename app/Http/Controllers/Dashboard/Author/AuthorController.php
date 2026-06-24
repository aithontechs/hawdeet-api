<?php

namespace App\Http\Controllers\Dashboard\Author;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Author\AuthorRequest;
use App\Models\User;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorController extends Controller
{
    use ResponseApi ;


    public function index(Request $request)
    {
        $authors = User::query()->where('is_author', 1)->select('id', 'name', 'avatar_url', 'is_active')
            ->withCount([
                'authorBooks as published_books_count' => function ($q) {
                    $q->where('published', true);
                },
                'followers'
            ])->latest()->search($request->search)->paginate(15);

        return $this->successApi(
            $authors,
            'Authors Fetched successfully'
        );
    }


    public function store(AuthorRequest $request)
    {
        $validated = $request->validated();
        $validated['email_verified_at'] = now() ;
        $validated['is_author'] = 1;
        $user = User::create($validated);
        return $this->successApi($user ,'Author Created successfully',201) ;
    }

    public function show($user_id)
    {
        $author = User::where('id' , $user_id )->where("is_author" , 1)->firstorfail() ;
        return $this->successApi($author,'Author fetched successfully') ;
    }

    public function update(AuthorRequest $request, User $author)
    {
        $author->update($request->validated());
        return $this->successApi($author ,'Author updated successfully') ;
    }

    public function destroy(User $user)
    {
        $booksCount = $user->authorBooks()->where('published', true)->count();
        
        if ($booksCount > 0) {
            return $this->errorApi(
                "لا يمكن حذف المؤلف لأنه يمتلك {$booksCount} كتاب منشور.",
                422
            );
        }
        $user->delete();
        return $this->successApi(null ,'Author deleted successfully') ;
    }

    public function list()
    {
        $author = User::select('id' , 'name')->where('is_author' , 1)->latest()->get();
        return $this->successApi($author , 'Authors Fetched successfully') ;
    }

    public function stats()
    {
        $stats = User::selectRaw("
            COUNT(*) as total_authors,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_authors,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_authors
        ", [now()->subDays(30)])
        ->where('is_author', 1)
        ->first();

        $avgBooks = DB::table('books')
                    ->selectRaw('AVG(book_count) as avg_books')
                    ->fromSub(
                        DB::table('books')
                            ->selectRaw('author_id, COUNT(*) as book_count')
                            ->groupBy('author_id'),
                        'authors_books'
                    )
                    ->value('avg_books');
        return $this->successApi([
            'total_authors' => $stats->total_authors,
            'active_authors' => $stats->active_authors,
            'new_authors' => $stats->new_authors,
            'average_books_per_author' => round($averageBooksPerAuthor ?? 0, 2),
        ], 'Stats of authors fetched successfully');
    }
}
