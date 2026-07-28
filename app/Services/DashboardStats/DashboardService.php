<?php

namespace App\Services\DashboardStats;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    private const CACHE_TTL = 60 * 6;

    public function getStats()
    {
        return [
            'totals'                => $this->getTotals(),
            'monthly_user_growth'   => $this->getMonthlyUserGrowth(),
            'category_distribution' => $this->getCategoryDistribution(),
            'top_selling_books'     => $this->topSellingBooks(),
            'recent_activities'     => $this->getRecentActivities(),
        ];
    }

    public function getTotals()
    {
        return Cache::remember('dashboard.totals', self::CACHE_TTL, function () {
            return [
                'total_users'         => User::where('is_author' , false )->count(),
                'total_books'         => Book::count(),
                'total_subscriptions' => UserSubscription::count(),
                'total_sales'         => [
                    'amount'   => (float) Order::where('payment_status', 'paid')->sum('total'),
                    'currency' => 'EGP',
                ],
            ];
        });
    }

    public function getMonthlyUserGrowth()
    {
        return Cache::remember('dashboard.monthly_user_growth.' . now()->year, self::CACHE_TTL, function () {
            $counts = User::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
                ->whereYear('created_at', now()->year)
                ->groupBy('month')
                ->pluck('total', 'month');

            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $months[] = [
                    'month' => now()->setMonth($m)->format('M'),
                    'total' => $counts[$m] ?? 0,
                ];
            }

            return $months;
        });
    }

    public function getCategoryDistribution(int $limit = 4)
    {
        return Cache::remember('dashboard.category_distribution.' . $limit, self::CACHE_TTL, function () use ($limit) {
            return Category::whereNull('parent_id')
                        ->withCount('books')
                        ->orderByDesc('books_count')
                        ->limit($limit)
                        ->get()
                        ->map(fn ($category) => [
                            'id'    => $category->id,
                            'name'  => $category->name,
                            'total' => $category->books_count,
                        ]);
        });
    }

    public function topSellingBooks(int $limit = 5)
    {
        return Cache::remember('dashboard.top_selling_books.' . $limit, self::CACHE_TTL, function () use ($limit) {
            return Book::query()
                    ->select('books.id', 'books.title', 'books.cover', 'books.avg_rating')
                    ->with('categories:id,name')
                    ->leftJoin('order_items', 'order_items.book_id', '=', 'books.id')
                    ->leftJoin('orders', function ($join) {
                        $join->on('orders.id', '=', 'order_items.order_id')
                            ->where('orders.payment_status', '=', 'paid');
                    })
                    ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as total_sold')
                    ->groupBy('books.id', 'books.title', 'books.cover', 'books.avg_rating')
                    ->orderByDesc('total_sold')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($book) => [
                        'id'          => $book->id,
                        'title'       => $book->title,
                        'cover_image' => $book->cover_url,
                        'rating'      => (float) $book->rating,
                        'total_sold'  => (int) $book->total_sold,
                        'categories'  => $book->categories->pluck('name'),
                    ]);
        });
    }


    public function getRecentActivities(int $limit = 5)
    {
        $orders = Order::select(['id', 'order_number', 'created_at', 'user_id'])
                    ->with('user:id,name')
                    ->latest()
                    ->limit($limit)
                    ->get()
                    ->map(fn ($order) => [
                        'type'       => 'order_created',
                        'message'    => 'placed a new order',
                        'user'       => $order->user?->name,
                        'ref'        => $order->order_number,
                        'created_at' => $order->created_at,
                    ]);

        $subscriptions = UserSubscription::select(['id', 'user_id', 'plan_id', 'created_at'])
                            ->with(['user:id,name', 'plan:id,name'])
                            ->latest()
                            ->limit($limit)
                            ->get()
                            ->map(fn ($subscription) => [
                                'type'       => 'subscription_created',
                                'message'    => 'subscribed to a plan',
                                'user'       => $subscription->user?->name,
                                'ref'        => $subscription->plan?->name,
                                'created_at' => $subscription->created_at,
                            ]);

        $users = User::select(['id', 'name', 'created_at'])
                    ->latest()
                    ->limit($limit)
                    ->get()
                    ->map(fn ($user) => [
                        'type'       => 'user_registered',
                        'message'    => 'created a new account',
                        'user'       => $user->name,
                        'ref'        => null,
                        'created_at' => $user->created_at,
                    ]);

        return $orders
            ->concat($subscriptions)
            ->concat($users)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->map(function ($activity) {
                $activity['created_at'] = $activity['created_at']->format('Y-m-d h:i:s');
                return $activity;
            });
    }

    public function clearCache(): void
    {
        Cache::forget('dashboard.totals');
        Cache::forget('dashboard.monthly_user_growth.' . now()->year);
        Cache::forget('dashboard.category_distribution');
        Cache::forget('dashboard.top_selling_books.5');
    }
}
