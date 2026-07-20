<?php

namespace App\Http\Controllers\Application\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Subscription\SubscriptionStoreRequest;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\Currency\CurrencyResolver;
use App\Services\Subscription\SubscriptionService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    use ResponseApi;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly CurrencyResolver $currencyResolver,
    ) {}

    public function index(Request $request)
    {
        $currency = $this->currencyResolver->resolve($request);
        $plans = SubscriptionPlan::latest()
            ->active()
            ->get()
            ->map(function ($plan) use ($currency) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'duration_months' => $plan->duration_months,
                    'price' => $plan->priceFor($currency),
                    'compare_price' => $plan->comparePriceFor($currency),
                    'currency' => $currency,
                    'description' => $plan->description,
                    'is_active' => $plan->is_active,
                ];
            });

        return $this->successApi($plans, 'Subscription plans fetched successfully');
    }

    public function store(SubscriptionStoreRequest $request)
    {
        $user = auth('user-api')->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $currency = $this->currencyResolver->resolve($request);

        abort_if($this->subscriptionService->hasActiveSubscription($user), 422, 'You already have an active subscription.');

        $coupon = null;
        if ($request->filled('coupon_code')) {
            $coupon = $this->subscriptionService->validateCouponForSubscription(
                $request->coupon_code,
                $plan->price,
                $user,
                $currency
            );
        }

        $pendingSubscription = UserSubscription::where('user_id', $user->id)
            ->where('payment_status', 'pending')
            ->where('plan_id', $plan->id)
            ->latest()
            ->first();

        if ($pendingSubscription) {
            $payment = Payment::where('user_subscription_id', $pendingSubscription->id)
                ->where('status', 'pending')
                ->where('currency', $currency)
                ->latest()
                ->first();

            if ($payment) {
                $paymentUrl = $this->subscriptionService->initiatePaymobPayment(
                    $payment, $request, $request->payment_method
                );

                return $this->successApi([
                    'payment_id'  => $payment->id,
                    'original_amount' => $pendingSubscription->original_amount,
                    'discount'        => $pendingSubscription->discount_amount,
                    'amount'      => $payment->amount,
                    'payment_url' => $paymentUrl,
                    'currency'    => $payment->currency,
                    'status'      => 'pending',
                    'resumed'     => true,
                ], 'Complete your pending payment.');
            }
        }

        $payment    = $this->subscriptionService->initiate($user, $plan, $coupon,$currency);
        $paymentUrl = $this->subscriptionService->initiatePaymobPayment($payment, $request, $request->payment_method);

        return $this->successApi([
            'payment_id'      => $payment->id,
            'original_amount' => $payment->subscription->original_amount,
            'discount'        => $payment->subscription->discount_amount,
            'amount'          => $payment->amount,
            'currency'        => $payment->currency,
            'payment_url'     => $paymentUrl,
            'status'          => 'pending',
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
            'coupon_code'    => 'nullable|string',
        ]);

        $user = auth('user-api')->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $currency = $this->currencyResolver->resolve($request);

        $hasPrevious = UserSubscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'expired'])
            ->where('payment_status', 'paid')
            ->exists();

        abort_unless($hasPrevious, 422, 'No previous subscription found to renew.');

        $coupon = null;
        if ($request->filled('coupon_code')) {
            $coupon = $this->subscriptionService->validateCouponForSubscription(
                $request->coupon_code,
                $plan->price,
                $user,
                $currency
            );
        }

        $pendingSubscription = UserSubscription::where('user_id', $user->id)
            ->where('payment_status', 'pending')
            ->where('plan_id', $plan->id)
            ->latest()
            ->first();

        if ($pendingSubscription) {
            $payment = Payment::where('user_subscription_id', $pendingSubscription->id)
                ->where('status', 'pending')
                ->where('currency', $currency)
                ->latest()
                ->first();

            if ($payment) {
                $paymentUrl = $this->subscriptionService->initiatePaymobPayment(
                    $payment, $request, $request->payment_method
                );

                return $this->successApi([
                    'payment_id'  => $payment->id,
                    'original_amount' => $pendingSubscription->original_amount,
                    'discount'        => $pendingSubscription->discount_amount,
                    'amount'      => $payment->amount,
                    'currency'    => $payment->currency,
                    'payment_url' => $paymentUrl,
                    'status'      => 'pending',
                    'resumed'     => true,
                ], 'Complete your pending renewal payment.');
            }
        }

        $payment    = $this->subscriptionService->renew($user, $plan, $coupon,$currency);
        $paymentUrl = $this->subscriptionService->initiatePaymobPayment(
            $payment, $request, $request->payment_method
        );

        return $this->successApi([
            'payment_id'      => $payment->id,
            'original_amount' => $pendingSubscription->original_amount,
            'discount'        => $pendingSubscription->discount_amount,
            'amount'          => $payment->amount,
            'currency'        => $payment->currency,
            'payment_url'     => $paymentUrl,
            'status'          => 'pending',
            'resumed'         => false,
        ], 'Complete your payment to renew your subscription.');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'plan_id'     => 'required|exists:subscription_plans,id',
            'coupon_code' => 'nullable|string',
        ]);

        $user = auth('user-api')->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $currency = $this->currencyResolver->resolve($request);

        $originalAmount = $plan->priceFor($currency);
        $discount       = 0;
        $couponData     = null;

        if ($request->filled('coupon_code')) {
            try {
                $coupon   = $this->subscriptionService->validateCouponForSubscription(
                    $request->coupon_code,
                    $originalAmount,
                    $user,
                    $currency
                );
                $discount = $this->subscriptionService->calculateCouponDiscount($coupon, $originalAmount,$currency);

                $couponData = [
                    'code'            => $coupon->code,
                    'discount_amount' => $discount,
                ];
            } catch (ValidationException $e) {
                return $this->errorApi($e->getMessage(), 422);
            }
        }

        $finalAmount = max(0, $originalAmount - $discount);

        return $this->successApi([
            'plan' => [
                'id'       => $plan->id,
                'name'     => $plan->name,
                'duration' => $plan->duration_months,
            ],
            'currency'        => $currency,
            'original_amount' => $originalAmount,
            'discount'        => $discount,
            'final_amount'    => $finalAmount,
            'coupon'          => $couponData,
        ], 'Subscription payment preview');
    }

    public function cancel(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|integer|exists:user_subscriptions,id',
        ]);
        $user = auth('user-api')->user();
        $this->subscriptionService->cancelPending($user, $request->subscription_id);
        return $this->successApi(null, 'Pending subscription cancelled successfully.');
    }
}
