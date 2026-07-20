<?php

namespace App\Http\Controllers\Application\Home;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookReadingProgress;
use App\Models\Category;
use App\Services\Book\UserBookService;
use App\Services\Currency\CurrencyResolver;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    use ResponseApi;

    private const BOOK_COLUMNS = [
        'id', 'title', 'type',
        'price', 'price_usd', 'compare_price', 'compare_price_usd',
        'physical_price', 'physical_price_usd', 'physical_compare_price', 'physical_compare_price_usd',
        'physical_stock',
        'physical_hard_cover_price', 'physical_hard_cover_price_usd',
        'physical_hard_cover_compare_price', 'physical_hard_cover_compare_price_usd',
        'physical_hard_cover_stock',
        'avg_rating', 'cover', 'author_id',
        'is_subscription_included', 'is_free',
    ];

    public function __construct(
        private readonly UserBookService $userBookService,
        private readonly CurrencyResolver $currencyResolver,
    ) {}

    public function index(Request $request)
    {
        $user          = auth()->user();
        $accessContext = $this->buildAccessContext($user);
        $currency      = $this->currencyResolver->resolve($request);

        return $this->successApi([
            'currency'    => $currency,
            'hero'        => $this->getHeroSection($user, $accessContext, $currency),
            'categories'  => $this->getCategories(),
            'suggestions' => $this->getSuggestedBooks($user, $accessContext, null, $currency),
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

    private function getHeroSection($user, array $accessContext, string $currency): array
    {
        if ($user) {
            $progress = BookReadingProgress::query()
                ->with([
                    'book' => fn($q) => $q->select([...self::BOOK_COLUMNS , 'description'])->with('author:id,name'),
                ])
                ->where('user_id', $user->id)
                ->whereNotNull('last_read_at')
                ->latest('last_read_at')
                ->first();


            if ($progress?->book) {
                $book = $this->formatBook($progress->book, $accessContext, $currency);
                $book['description'] = $progress->book->description;
                return [
                    'type'     => $progress->status === 'completed'
                                    ? 'completed'
                                    : 'continue_reading',
                    'book'     => $book ,
                    'progress' => [
                        'current_page' => $progress->current_page,
                        'total_pages'  => $progress->total_pages,
                        'percentage'   => $progress->percentage,
                        'status'       => $progress->status,
                    ],
                ];
            }
        }

        $latest = Book::select([...self::BOOK_COLUMNS , 'description'])
            ->with('author:id,name')
            ->where('published', true)
            ->latest()
            ->first();

        $book = null;

        if ($latest) {
            $book = $this->formatBook($latest, $accessContext, $currency);
            $book['description'] = $latest->description;
        }
        return [
            'type'     => 'latest_release',
            'book'     => $book ,
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

    private function getSuggestedBooks($user, array $accessContext, ?int $categoryId = null, string $currency = 'EGP'): array
    {
        $query = Book::select(self::BOOK_COLUMNS)
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
            ->flatMap(fn($book) => $this->expandBook($book, $accessContext, $currency))
            ->toArray();
    }

    public function categoryBooks(Request $request, Category $category)
    {
        $user          = auth()->user();
        $accessContext = $this->buildAccessContext($user);
        $currency      = $this->currencyResolver->resolve($request);

        $books = Book::select(self::BOOK_COLUMNS)
            ->with('author:id,name')
            ->where('published', true)
            ->whereHas('categories', fn($q) =>
                $q->where('categories.id', $category->id)
            )
            ->orderByDesc('avg_rating')
            ->paginate(10);

        $formattedBooks = collect($books->items())
            ->flatMap(fn($book) => $this->expandBook($book, $accessContext, $currency))
            ->values();

        return $this->successApi([
            'category'   => ['id' => $category->id, 'name' => $category->name],
            'currency'   => $currency,
            'books'      => $formattedBooks,
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

    private function formatBook(Book $book, array $accessContext, string $currency, string $typeOverride = null): array
    {
        $hasDirect          = in_array($book->id, $accessContext['accessibleBookIds']);
        $hasViaSubscription = $accessContext['hasSubscription'] && $book->is_subscription_included;

        $type = $typeOverride ?? $book->type;

        $data = [
            'id'         => $book->id,
            'title'      => $book->title,
            'type'       => $type,
            'avg_rating' => $book->avg_rating,
            'author_id'  => $book->author_id,
            'is_subscription_included' => $book->is_subscription_included,
            'is_free'   => $book->is_free,
            'cover_url' => $book->cover_url,
            'author'    => $book->author ? ['id' => $book->author->id, 'name' => $book->author->name] : null,
            'access' => [
                'has_access'   => $hasDirect || $hasViaSubscription || $book->is_free,
                'via_purchase'     => $hasDirect,
                'via_subscription' => $hasViaSubscription,
                'has_subscription' => $accessContext['hasSubscription'],
            ],
        ];

        if ($type === 'digital') {
            $data['price']   = $book->digitalPriceFor($currency);
            $data['compare_price']  = $book->comparePriceFor('compare_price', $currency);
            $data['physical_price'] = null;
            $data['physical_compare_price'] = null;
            $data['physical_stock']  = 0;
        } elseif ($type === 'physical') {
            $data['price'] = null;
            $data['compare_price']  = null;
            $data['physical_price'] = $book->physicalPriceFor('normal', $currency);
            $data['physical_compare_price']     = $book->comparePriceFor('physical_compare_price', $currency);
            $data['physical_stock']  = $book->physical_stock;
            $data['physical_hard_cover_price']  = $book->physicalPriceFor('hard_cover', $currency);
            $data['physical_hard_cover_compare_price']   = $book->comparePriceFor('physical_hard_cover_compare_price', $currency); // 🆕 إصلاح
            $data['physical_hard_cover_stock']    = $book->physical_hard_cover_stock;
        } else {
            $data['price'] = $book->digitalPriceFor($currency);
            $data['compare_price']   = $book->comparePriceFor('compare_price', $currency);
            $data['physical_price']  = $book->physicalPriceFor('normal', $currency);
            $data['physical_compare_price']           = $book->comparePriceFor('physical_compare_price', $currency);
            $data['physical_stock']                   = $book->physical_stock;
            $data['physical_hard_cover_price']        = $book->physicalPriceFor('hard_cover', $currency);
            $data['physical_hard_cover_compare_price']= $book->comparePriceFor('physical_hard_cover_compare_price', $currency);
            $data['physical_hard_cover_stock']        = $book->physical_hard_cover_stock;
        }

        return $data;
    }

    private function expandBook(Book $book, array $accessContext, string $currency): array
    {
        if ($book->type === 'both') {
            return [
                $this->formatBook($book, $accessContext, $currency, 'digital'),
                $this->formatBook($book, $accessContext, $currency, 'physical'),
            ];
        }

        return [$this->formatBook($book, $accessContext, $currency)];
    }
}
