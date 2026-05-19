<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Models\Book;
use App\Models\BookReview;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Policies\BookAccessPolicy;
use App\Policies\BookPolicy;
use App\Policies\ReviewModifyPolicy;
use App\Policies\RolePolicy;
use App\Policies\SubscriptionPlanPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Role::class => RolePolicy::class,
        SubscriptionPlan::class => SubscriptionPlanPolicy::class,
        BookReview::class => ReviewModifyPolicy::class ,
        Book::class => BookPolicy::class ,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}
