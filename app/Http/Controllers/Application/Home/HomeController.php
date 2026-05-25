<?php

namespace App\Http\Controllers\Application\Home;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookReadingProgress;
use App\Models\Category;
use App\Services\Book\UserBookService;
use App\Traits\ResponseApi;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    use ResponseApi;

    public function __construct(private readonly UserBookService $userBookService) {}

    public function index()
    {
        $user = auth()->user();
        $accessContext = $this->buildAccessContext($user);

        return $this->successApi([
            'hero'        => $this->getHeroSection($user, $accessContext),
            'categories'  => $this->getCategories(),
            'suggestions' => $this->getSuggestedBooks($user, $accessContext),
        ], 'Home fetched successfully');
    }

    private function buildAccessContext($user): array
    {
        if (!$user) {
            return ['hasSubscription' => false, 'accessibleBookIds' => []];
        }

        return [
            'hasSubscription'   => $this->userBookService->hasActiveSubscription($user),
            'accessibleBookIds' => $this->userBookService->getUserBookIds($user),
        ];
    }


    private function getHeroSection($user, array $accessContext): array
    {
        if ($user) {
            $progress = BookReadingProgress::query()
                                ->with([
                                    'book' => fn($q) => $q->select([
                                        'id','title','type','price','compare_price',
                                        'physical_price','physical_compare_price',
                                        'physical_stock','avg_rating','cover',
                                        'author_id',
                                    ])->with('author:id,name'),
                                ])
                                ->where('user_id', $user->id)
                                ->whereNotNull('last_read_at')
                                ->latest('last_read_at')
                                ->first();

            if ($progress?->book) {
                return [
                    'type'     => $progress->status === 'completed'
                                    ? 'completed'
                                    : 'continue_reading',
                    'book'     => $this->formatBook($progress->book, $accessContext),
                    'progress' => [
                        'current_page' => $progress->current_page,
                        'total_pages'  => $progress->total_pages,
                        'percentage'   => $progress->percentage,
                        'status'       => $progress->status,
                    ],
                ];
            }
        }

        $latest = Book::select([
                'id','title','type','price','compare_price',
                'physical_price','physical_compare_price',
                'physical_stock','avg_rating','cover',
                'author_id'
            ])
            ->with('author:id,name')
            ->where('published', true)
            ->latest()
            ->first();

        return [
            'type'     => 'latest_release',
            'book'     => $latest ? $this->formatBook($latest, $accessContext) : null,
            'progress' => null,
        ];
    }


    private function getCategories(): array
    {
        return Category::query()
            ->select(['id', 'name', 'parent_id'])
            ->whereNull('parent_id')
            ->withCount(['books' => fn($q) => $q->where('published', true)])
            ->latest()
            ->get()
            ->toArray();
    }

    private function getSuggestedBooks($user, array $accessContext, ?int $categoryId = null): array
    {
        $query = Book::select([
                        'id','title','type','price','compare_price',
                        'physical_price','physical_compare_price',
                        'physical_stock','avg_rating','cover',
                        'author_id','is_subscription_included','is_free',
                    ])
                    ->with('author:id,name')
                    ->where('published', true);

        if ($categoryId) {
            $query->whereHas('categories', fn($q) =>
                $q->where('categories.id', $categoryId)
            );
        } elseif ($user) {
            $readCategoryIds = DB::table('book_reading_progress')
                ->join('book_categories', 'book_reading_progress.book_id', '=', 'book_categories.book_id')
                ->where('book_reading_progress.user_id', $user->id)
                ->pluck('book_categories.category_id')
                ->unique()
                ->values()
                ->toArray();

            if (!empty($readCategoryIds)) {
                $query->whereHas('categories', fn($q) =>
                    $q->whereIn('categories.id', $readCategoryIds)
                );
            }
        }

        return $query->orderByDesc('avg_rating')->limit(10)->get()
                        ->map(fn($book) => $this->formatBook($book, $accessContext))
                        ->toArray();
    }

    public function categoryBooks(Category $category)
    {
        $user          = auth()->user();
        $accessContext = $this->buildAccessContext($user);

        $books = Book::select([
                        'id','title','type','price','compare_price',
                        'physical_price','physical_compare_price',
                        'physical_stock','avg_rating','cover',
                        'author_id','is_subscription_included','is_free',
                    ])
                    ->with('author:id,name')
                    ->where('published', true)
                    ->whereHas('categories', fn($q) =>
                        $q->where('categories.id', $category->id)
                    )
                    ->orderByDesc('avg_rating')
                    ->paginate(10);

        $books->through(fn($book) => $this->formatBook($book, $accessContext));

        return $this->successApi([
            'category'   => ['id' => $category->id, 'name' => $category->name],
            'books'      => $books->items(),
            'pagination' => [
                'current_page'  => $books->currentPage(),
                'last_page'     => $books->lastPage(),
                'per_page'      => $books->perPage(),
                'total'         => $books->total(),
                'next_page_url' => $books->nextPageUrl(),
                'prev_page_url' => $books->previousPageUrl(),
            ],
        ], 'Category books fetched successfully');
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
}
