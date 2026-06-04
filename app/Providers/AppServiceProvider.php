<?php

namespace App\Providers;

use App\Models\BookReview;
use App\Models\UserBook;
use App\Models\UserSubscription;
use App\Observers\BookReviewObserver;
use App\Observers\UserBookObserver;
use App\Observers\UserSubscriptionObserver;
use App\Services\Pusher\PusherService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->singleton(PusherService::class, fn() => new PusherService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        BookReview::observe(BookReviewObserver::class);
        UserSubscription::observe(UserSubscriptionObserver::class);
        UserBook::observe(UserBookObserver::class);
        //specify long string
            \Illuminate\Database\Schema\Builder::defaultStringLength(191);

    }
}
