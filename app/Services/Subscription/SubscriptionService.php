<?php

namespace App\Services\Subscription;

use App\Models\{Payment, SubscriptionPlan, User, UserSubscription};
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function initiate(User $user, SubscriptionPlan $plan): Payment
    {
        return DB::transaction(function () use ($user, $plan) {

            $subscription = UserSubscription::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'price'          => $plan->price,
                'start_at'       => now(),
                'end_at'         => now()->addMonths($plan->duration_months),
                'status'         => 'inactive',
                'payment_status' => 'pending',
            ]);

            return Payment::create([
                'user_id'              => $user->id,
                'user_subscription_id' => $subscription->id,
                'amount'               => $plan->price,
                'currency'             => 'EGP',
                'type'                 => 'subscription',
                'status'               => 'pending',
            ]);
        });
    }

    public function renew(User $user, SubscriptionPlan $plan): Payment
    {
        return DB::transaction(function () use ($user, $plan) {

            $current = UserSubscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'expired'])
                ->where('payment_status', 'paid')
                ->latest('end_at')
                ->first();

            $startAt = $current && $current->end_at->isFuture()
                ? $current->end_at
                : now();

            $subscription = UserSubscription::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'price'          => $plan->price,
                'start_at'       => $startAt,
                'end_at'         => $startAt->copy()->addMonths($plan->duration_months),
                'status'         => 'inactive',
                'payment_status' => 'pending',
            ]);

            return Payment::create([
                'user_id'              => $user->id,
                'user_subscription_id' => $subscription->id,
                'amount'               => $plan->price,
                'currency'             => 'EGP',
                'type'                 => 'subscription',
                'status'               => 'pending',
            ]);
        });
    }

    public function activate(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {

            $payment->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            $subscription = $payment->userSubscription;

            $subscription->update([
                'status'         => 'active',
                'payment_status' => 'paid',
            ]);

            $previousExpiredSub = UserSubscription::query()
                                    ->where('user_id', $subscription->user_id)
                                    ->where('id', '!=', $subscription->id)
                                    ->where('status', 'expired')
                                    ->latest('end_at')
                                    ->first();

            if ($previousExpiredSub) {
                UserBook::query()
                    ->where('user_subscription_id', $previousExpiredSub->id)
                    ->where('access_type', 'subscription')
                    ->update([
                        'user_subscription_id' => $subscription->id,
                        'expires_at'           => $subscription->end_at,
                    ]);
            }
        });
    }

    public function markFailed(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {

            $payment->update([
                'status'         => 'failed',
                'failure_reason' => $reason,
            ]);

            $payment->userSubscription->update([
                'payment_status' => 'failed',
            ]);
        });
    }

    public function hasActiveSubscription(User $user): bool
    {
        return UserSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('end_at', '>', now())
                    ->exists();
    }

    // public function upgrade(User $user, SubscriptionPlan $newPlan)
    // {
    //     return DB::transaction(function () use ($user, $newPlan) {

    //         $current = UserSubscription::query()
    //             ->where('user_id', $user->id)
    //             ->where('status', 'active')
    //             ->where('end_at', '>', now())
    //             ->firstOrFail();

    //         $current->update([
    //             'status'      => 'inactive',
    //             'canceled_at' => now(),
    //             'ended_reason'=> 'upgraded_to_plan_' . $newPlan->id,
    //         ]);

    //         $subscription = UserSubscription::create([
    //             'user_id'        => $user->id,
    //             'plan_id'        => $newPlan->id,
    //             'price'          => $newPlan->price,
    //             'start_at'       => now(),
    //             'end_at'         => now()->addMonths($newPlan->duration_months),
    //             'status'         => 'inactive',
    //             'payment_status' => 'pending',
    //         ]);

    //         return Payment::create([
    //             'user_id'              => $user->id,
    //             'user_subscription_id' => $subscription->id,
    //             'amount'               => $newPlan->price,
    //             'currency'             => 'EGP',
    //             'type'                 => 'subscription',
    //             'status'               => 'pending',
    //         ]);
    //     });
    // }
}
