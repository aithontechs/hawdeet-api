<?php

namespace App\Http\Controllers\Application\Book;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Book\BookFilterRequest;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Services\Book\UserBookService;
use App\Services\Currency\CurrencyResolver;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class BookController extends Controller
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

    public function __construct(private readonly UserBookService $userBookService , private readonly CurrencyResolver $currencyResolver) {}

    public function index(BookFilterRequest $request)
    {
        $user          = auth()->user();
        $accessContext = $this->buildAccessContext($user);
        $currency  = $this->currencyResolver->resolve($request);

        $books = Book::select(self::BOOK_COLUMNS)
            ->with('author:id,name')
            ->where('published', true)
            ->when($request->filled('search'), fn($q) =>
                $q->where(function ($q) use ($request) {
                    $q->where('title', 'like', "%{$request->search}%")
                      ->orWhereHas('author', fn($q) =>
                          $q->where('name', 'like', "%{$request->search}%")
                      )->orWhereHas('categories', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%")

                            ->orWhereHas('parent', function ($q) use ($request) {
                                $q->where('name', 'like', "%{$request->search}%");
                            })

                            ->orWhereHas('children', function ($q) use ($request) {
                                $q->where('name', 'like', "%{$request->search}%");
                            });
                    });
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
            ->when($request->filled('price_min'), fn($q) =>
                $q->where($currency === 'USD' ? 'price_usd' : 'price', '>=', $request->price_min)
            )
            ->when($request->filled('price_max'), fn($q) =>
                $q->where($currency === 'USD' ? 'price_usd' : 'price', '<=', $request->price_max)
            )
            ->when($request->filled('rating_min'), fn($q) =>
                $q->where('avg_rating', '>=', $request->rating_min)
            )
            ->when($request->filled('type'), fn($q) =>
                $q->where('type', $request->type)
            )
            ->when(true, function ($q) use ($request, $currency) {
                $priceColumn = $currency === 'USD' ? 'price_usd' : 'price';
                match ($request->sort ?? 'latest') {
                    'price_asc'  => $q->orderBy($priceColumn, 'asc'),
                    'price_desc' => $q->orderBy($priceColumn, 'desc'),
                    'rating'     => $q->orderByDesc('avg_rating'),
                    default      => $q->latest(),
                };
            })
            ->paginate(15);

        $formattedBooks = collect();
        foreach ($books->items() as $book) {
            if ($book->type === 'both') {
                $formattedBooks->push($this->formatBook($book, $accessContext, $currency, 'digital'));
                $formattedBooks->push($this->formatBook($book, $accessContext, $currency, 'physical'));
            } else {
                $formattedBooks->push($this->formatBook($book, $accessContext, $currency));
            }
        }

        return $this->successApi([
            'books'      => $formattedBooks->values(),
            'currency'   => $currency,
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

    public function show(Request $request , Book $book)
    {
        $user = auth()->user();
        $accessContext = $this->buildAccessContext($user);
        $currency    = $this->currencyResolver->resolve($request);
        $data = $this->formatBook($book, $accessContext, $currency);
        $data['description'] = $book->description;
        $data['currency'] = $currency ;
        return $this->successApi($data, 'Book details fetched successfully');
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

    private function formatBook(Book $book, array $accessContext ,string $currency, string $typeOverride = null): array
    {
        $hasDirect = in_array($book->id, $accessContext['accessibleBookIds']);
        $hasViaSubscription = $accessContext['hasSubscription'] && $book->is_subscription_included;

        $type = $typeOverride ?? $book->type;

        $data = [
            'id'                       => $book->id,
            'title'                    => $book->title,
            'type'                     => $type,
            'avg_rating'               => $book->avg_rating,
            'author_id'                => $book->author_id,
            'is_subscription_included' => $book->is_subscription_included,
            'is_free'                  => $book->is_free,
            'cover_url'                => $book->cover_url,
            'author' => $book->author ? ['id' => $book->author->id, 'name' => $book->author->name] : null,
            'access' => [
                'has_access'       => $hasDirect || $hasViaSubscription || $book->is_free,
                'via_purchase'     => $hasDirect,
                'via_subscription' => $hasViaSubscription,
                'has_subscription' => $accessContext['hasSubscription'],
            ],
        ];

        if ($type === 'digital') {
            $data['price']    = $book->digitalPriceFor($currency);
            $data['compare_price'] = $book->comparePriceFor('compare_price', $currency);
            $data['physical_price']  = null;
            $data['physical_compare_price'] = null;
            $data['physical_stock']  = 0;
        } elseif ($type === 'physical') {
            $data['price']         = null;
            $data['compare_price'] = null;
            $data['physical_price']         = $book->physicalPriceFor('normal', $currency);
            $data['physical_compare_price'] = $book->comparePriceFor('physical_compare_price', $currency);
            $data['physical_stock']         = $book->physical_stock;
            $data['physical_hard_cover_price'] = $book->physicalPriceFor('hard_cover', $currency);
            $data['physical_hard_cover_compare_price'] = $book->comparePriceFor($book, 'physical_hard_cover_compare_price', $currency);
            $data['physical_hard_cover_stock'] = $book->physical_hard_cover_stock;
        }else{
            $data['price']   = $book->digitalPriceFor($currency);
            $data['compare_price'] = $book->comparePriceFor('compare_price', $currency);
            $data['physical_price']    = $book->physicalPriceFor('normal', $currency);
            $data['physical_compare_price'] = $book->comparePriceFor('physical_compare_price', $currency);
            $data['physical_stock']         = $book->physical_stock;
            $data['physical_hard_cover_price'] = $book->physicalPriceFor('hard_cover', $currency);
            $data['physical_hard_cover_compare_price']= $book->comparePriceFor('physical_hard_cover_compare_price', $currency);
            $data['physical_hard_cover_stock'] = $book->physical_hard_cover_stock;
        }

        return $data;
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
