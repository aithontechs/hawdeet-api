<?php

namespace App\Providers;

use App\Models\BookReview;
use App\Models\UserSubscription;
use App\Observers\BookReviewObserver;
use App\Observers\UserSubscriptionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        BookReview::observe(BookReviewObserver::class);
        UserSubscription::observe(UserSubscriptionObserver::class);
    }
}
