<?php

namespace App\Http\Controllers\Application\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Subscription\SubscriptionStoreRequest;
use App\Models\Payment;
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

        abort_if($this->subscriptionService->hasActiveSubscription($user),422,'You already have an active subscription.');
        $pendingSubscription = UserSubscription::where('user_id', $user->id)->where('payment_status', 'pending')->where('plan_id', $plan->id)->latest()->first();

        if ($pendingSubscription) {
            $payment = Payment::where('user_subscription_id', $pendingSubscription->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($payment) {
                $paymentUrl = $this->subscriptionService->initiatePaymobPayment(
                    $payment, $request, $request->payment_method
                );

                return $this->successApi([
                    'payment_id'  => $payment->id,
                    'amount'      => $payment->amount,
                    'payment_url' => $paymentUrl,
                    'status'      => 'pending',
                    'resumed'     => true,
                ], 'Complete your pending payment.');
            }
        }

        $payment  = $this->subscriptionService->initiate($user, $plan);
        $paymentUrl = $this->subscriptionService->initiatePaymobPayment($payment, $request, $request->payment_method);

        return $this->successApi([
            'payment_id'  => $payment->id,
            'amount'      => $payment->amount,
            'payment_url' => $paymentUrl,
            'status'      => 'pending',
        ], 'Complete your payment to activate the subscription.');
    }

    public function renew(Request $request)
    {
        $request->validate([
            'plan_id'        => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|in:card,wallet',
            'first_name'     => 'nullable|string',
            'last_name'      => 'nullable|string',
            'email'          => 'nullable|email',
            'phone'          => 'nullable|digits:11',
        ]);

        $user = auth('user-api')->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        $hasPrevious = UserSubscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'expired'])
            ->where('payment_status', 'paid')
            ->exists();

        abort_unless($hasPrevious, 422, 'No previous subscription found to renew.');

        $pendingSubscription = UserSubscription::where('user_id', $user->id)
            ->where('payment_status', 'pending')
            ->where('plan_id', $plan->id)
            ->latest()
            ->first();

        if ($pendingSubscription) {
            $payment = Payment::where('user_subscription_id', $pendingSubscription->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($payment) {
                $paymentUrl = $this->subscriptionService->initiatePaymobPayment(
                    $payment, $request, $request->payment_method
                );

                return $this->successApi([
                    'payment_id'  => $payment->id,
                    'amount'      => $payment->amount,
                    'payment_url' => $paymentUrl,
                    'status'      => 'pending',
                    'resumed'     => true,
                ], 'Complete your pending renewal payment.');
            }
        }

        $payment    = $this->subscriptionService->renew($user, $plan);
        $paymentUrl = $this->subscriptionService->initiatePaymobPayment(
            $payment, $request, $request->payment_method
        );

        return $this->successApi([
            'payment_id'  => $payment->id,
            'amount'      => $payment->amount,
            'payment_url' => $paymentUrl,
            'status'      => 'pending',
            'resumed'     => false,
        ], 'Complete your payment to renew your subscription.');
    }
}
