<?php

namespace App\Http\Controllers\Application\Book;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Book\BookFilterRequest;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Services\Book\UserBookService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class BookController extends Controller
{
    use ResponseApi;

    public function __construct(private readonly UserBookService $userBookService) {}

    public function index(BookFilterRequest $request)
    {
        $user          = auth()->user();
        $accessContext = $this->buildAccessContext($user);

        $books = Book::select([
                'id','title','type','price','compare_price',
                'physical_price','physical_compare_price',
                'physical_stock','avg_rating','cover','author_id',
                'is_subscription_included','is_free',
            ])
            ->with('author:id,name')
            ->where('published', true)
            ->when($request->filled('search'), fn($q) =>
                $q->where(function ($q) use ($request) {
                    $q->where('title', 'like', "%{$request->search}%")
                      ->orWhereHas('author', fn($q) =>
                          $q->where('name', 'like', "%{$request->search}%")
                      );
                })
            )
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $category    = Category::findOrFail($request->category_id);
                $categoryIds = $this->getCategoryWithChildrenIds($category);
                $q->whereHas('categories', fn($q) =>
                    $q->whereIn('categories.id', $categoryIds)
                );
            })
            ->when($request->filled('author_id'), fn($q) =>
                $q->where('author_id', $request->author_id)
            )
            ->when($request->filled('language'), fn($q) =>
                $q->where('language', $request->language)
            )
            ->when($request->filled('price_min'), fn($q) =>
                $q->where('price', '>=', $request->price_min)
            )
            ->when($request->filled('price_max'), fn($q) =>
                $q->where('price', '<=', $request->price_max)
            )
            ->when($request->filled('rating_min'), fn($q) =>
                $q->where('avg_rating', '>=', $request->rating_min)
            )
            ->when($request->filled('type'), fn($q) =>
                $q->where('type', $request->type)
            )
            ->when(true, function ($q) use ($request) {
                match ($request->sort ?? 'latest') {
                    'price_asc'  => $q->orderBy('price', 'asc'),
                    'price_desc' => $q->orderBy('price', 'desc'),
                    'rating'     => $q->orderByDesc('avg_rating'),
                    default      => $q->latest(),
                };
            })
            ->paginate(15);

        $books->through(fn($book) => $this->formatBook($book, $accessContext));

        return $this->successApi([
            'books'      => $books->items(),
            'pagination' => [
                'current_page'  => $books->currentPage(),
                'last_page'     => $books->lastPage(),
                'per_page'      => $books->perPage(),
                'total'         => $books->total(),
                'next_page_url' => $books->nextPageUrl(),
                'prev_page_url' => $books->previousPageUrl(),
            ],
        ], 'Books fetched successfully');
    }

    public function show(Book $book)
    {
        $user = auth()->user();
        $accessContext = $this->buildAccessContext($user);
        return $this->successApi($this->formatBook($book, $accessContext), 'Book details fetched successfully');
    }

    public function authors(Request $request)
    {
        $authors = User::select(['id', 'name'])
            ->where('is_author', true)
            ->when($request->filled('search'), fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
            )
            ->orderBy('name')
            ->limit(20)
            ->get();

        return $this->successApi($authors, 'Authors fetched successfully');
    }

    private function buildAccessContext($user): array
    {
        if (!$user) {
            return ['hasSubscription' => false, 'accessibleBookIds' => []];
        }

        return [
            'hasSubscription'   => $user->hasActiveSubscription(),
            'accessibleBookIds' => $this->userBookService->getUserBookIds($user),
        ];
    }

    private function formatBook(Book $book, array $accessContext): array
    {
        $hasDirect          = in_array($book->id, $accessContext['accessibleBookIds']);
        $hasViaSubscription = $accessContext['hasSubscription'] && $book->is_subscription_included;

        return array_merge($book->toArray(), [
            'access' => [
                'has_access'       => $hasDirect || $hasViaSubscription || $book->is_free,
                'via_purchase'     => $hasDirect,
                'via_subscription' => $hasViaSubscription,
                'has_subscription' => $accessContext['hasSubscription'],
            ],
        ]);
    }

    private function getCategoryWithChildrenIds(Category $category): array
    {
        $ids     = [$category->id];
        $flatten = function ($cats) use (&$flatten, &$ids) {
            foreach ($cats as $cat) {
                $ids[] = $cat->id;
                if ($cat->childrenRecursive) {
                    $flatten($cat->childrenRecursive);
                }
            }
        };

        $flatten($category->childrenRecursive);
        return $ids;
    }
}
