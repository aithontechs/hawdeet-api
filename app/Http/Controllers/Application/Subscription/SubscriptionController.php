<?php

namespace App\Http\Controllers\Application\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Subscription\SubscriptionStoreRequest;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\Subscription\SubscriptionService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use ResponseApi;

    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

    public function index()
    {
        $subscriptions = SubscriptionPlan::latest()->active()->get();

        return $this->successApi($subscriptions, 'Subscription plans fetched successfully');
    }

    public function store(SubscriptionStoreRequest $request)
    {
        $user = auth('user-api')->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        $hasPending = UserSubscription::query()->where('user_id', $user->id)->where('payment_status', 'pending')->exists();
        abort_if($hasPending, 422, 'You already have a pending subscription.');
        abort_if($this->subscriptionService->hasActiveSubscription($user),422,'You already have an active subscription.');

        $payment = $this->subscriptionService->initiate($user, $plan);

        return $this->successApi([
            'payment_id'  => $payment->id,
            'amount'      => $payment->amount,
            'payment_url' => route('payment.pay', $payment->id),
        ], 'Complete your payment to activate the subscription.');
    }

    public function renew(Request $request)
    {
        $user = auth('user-api')->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        $hasPrevious = UserSubscription::query()->where('user_id', $user->id)->whereIn('status', ['active', 'expired'])->where('payment_status', 'paid')->exists();

        abort_unless($hasPrevious, 422, 'No previous subscription found to renew.');

        $hasPending = UserSubscription::query()->where('user_id', $user->id)->where('payment_status', 'pending')->exists();

        abort_if($hasPending, 422, 'You already have a pending renewal.');

        $payment = $this->subscriptionService->renew($user, $plan);

        return $this->successApi([
            'payment_id'  => $payment->id,
            'amount'      => $payment->amount,
            'payment_url' => route('payment.pay', $payment->id),
        ], 'Complete your payment to renew your subscription.');
    }
}
