<?php

namespace App\Observers;

use App\Models\{UserBook, UserSubscription};
use App\Services\Book\UserBookService;

class UserSubscriptionObserver
{
    public function __construct(private readonly UserBookService $userBookService) {}

    public function created(UserSubscription $subscription): void
    {
        $this->userBookService->clearCache($subscription->user_id);
    }
    
    public function updated(UserSubscription $subscription): void
    {
        if (!$subscription->wasChanged(['status', 'end_at'])) {
            return;
        }

        if ($subscription->status === 'expired')
        {
            $this->expireSubscriptionBooks($subscription);
        }

        if ($subscription->wasChanged('end_at') && $subscription->status === 'active') {
            $this->extendSubscriptionBooks($subscription);
        }

        $this->userBookService->clearCache($subscription->user_id);
    }

    public function deleted(UserSubscription $subscription): void
    {
        $this->expireSubscriptionBooks($subscription);
        $this->userBookService->clearCache($subscription->user_id);
    }

    private function expireSubscriptionBooks(UserSubscription $subscription): void
    {
        $newActiveSub = UserSubscription::query()
                            ->where('user_id', $subscription->user_id)
                            ->where('id', '!=', $subscription->id)
                            ->where('status', 'active')
                            ->where('end_at', '>', now())
                            ->latest('end_at')
                            ->first();

        if ($newActiveSub) {
            UserBook::query()
                ->where('user_subscription_id', $subscription->id)
                ->where('access_type', 'subscription')
                ->update([
                    'user_subscription_id' => $newActiveSub->id,
                    'expires_at'           => $newActiveSub->end_at,
                ]);
        } else {
            UserBook::query()
                ->where('user_subscription_id', $subscription->id)
                ->where('access_type', 'subscription')
                ->update(['expires_at' => now()]);
        }
    }


    private function extendSubscriptionBooks(UserSubscription $subscription): void
    {
        UserBook::query()
            ->where('user_subscription_id', $subscription->id)
            ->where('access_type', 'subscription')
            ->update(['expires_at' => $subscription->end_at]);
    }
}
