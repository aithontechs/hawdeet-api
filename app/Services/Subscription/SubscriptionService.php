<?php

namespace App\Services\Subscription;

use App\Models\{Payment, SubscriptionPlan, User, UserSubscription};
use App\Models\Coupon;
use App\Models\UserBook;
use App\Services\Coupon\CouponService;
use App\Services\Currency\ExchangeRateService;
use App\Services\Payment\PaymobService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        private PaymobService $paymob,
        private readonly CouponService $couponService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    public function initiate(User $user, SubscriptionPlan $plan, ?Coupon $coupon = null, string $currency = 'EGP'): Payment
    {
        return DB::transaction(function () use ($user, $plan, $coupon,$currency) {

            $originalAmount = $plan->priceFor($currency);
            $discount       = $coupon
                ? $this->couponService->calculateDiscount($coupon, $originalAmount , $currency)
                : 0;
            $finalAmount    = max(0, $originalAmount - $discount);

            $subscription = UserSubscription::create([
                'user_id'         => $user->id,
                'plan_id'         => $plan->id,
                'coupon_id'       => $coupon?->id,
                'price'           => $finalAmount,
                'original_amount' => $originalAmount,
                'discount_amount' => $discount,
                'start_at'        => now(),
                'end_at'          => now()->addMonths($plan->duration_months),
                'status'          => 'inactive',
                'payment_status'  => 'pending',
            ]);

            return $this->createPendingPayment($subscription, $user, $originalAmount, $discount, $finalAmount, $coupon, $currency);

            // return Payment::create([
            //     'user_id'              => $user->id,
            //     'user_subscription_id' => $subscription->id,
            //     'original_amount'      => $originalAmount,
            //     'discount_amount'      => $discount,
            //     'amount'               => $finalAmount,
            //     'coupon_id'            => $coupon?->id,
            //     'currency'             => 'EGP',
            //     'type'                 => 'subscription',
            //     'status'               => 'pending',
            //     'payment_gateway'      => 'paymob',
            // ]);
        });
    }

    public function renew(User $user, SubscriptionPlan $plan, ?Coupon $coupon = null , string $currency = 'EGP'): Payment
    {
        return DB::transaction(function () use ($user, $plan, $coupon , $currency){

            $current = UserSubscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'expired'])
                ->where('payment_status', 'paid')
                ->latest('end_at')
                ->first();

            $startAt = $current && $current->end_at->isFuture()
                ? $current->end_at
                : now();

            $originalAmount = $plan->priceFor($currency);
            $discount       = $coupon
                ? $this->couponService->calculateDiscount($coupon, $originalAmount,$currency)
                : 0;
            $finalAmount    = max(0, $originalAmount - $discount);

            $subscription = UserSubscription::create([
                'user_id'         => $user->id,
                'plan_id'         => $plan->id,
                'coupon_id'       => $coupon?->id,
                'price'           => $finalAmount,
                'original_amount' => $originalAmount,
                'discount_amount' => $discount,
                'start_at'        => $startAt,
                'end_at'          => $startAt->copy()->addMonths($plan->duration_months),
                'status'          => 'inactive',
                'payment_status'  => 'pending',
            ]);

            return $this->createPendingPayment($subscription, $user, $originalAmount, $discount, $finalAmount, $coupon, $currency);

            // return Payment::create([
            //     'user_id'              => $user->id,
            //     'user_subscription_id' => $subscription->id,
            //     'original_amount'      => $originalAmount,
            //     'discount_amount'      => $discount,
            //     'amount'               => $finalAmount,
            //     'coupon_id'            => $coupon?->id,
            //     'currency'             => 'EGP',
            //     'type'                 => 'subscription',
            //     'status'               => 'pending',
            //     'payment_gateway'      => 'paymob',
            // ]);
        });
    }

    public function activate(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {

            $payment->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            $subscription = $payment->subscription;

            $subscription->update([
                'status'         => 'active',
                'payment_status' => 'paid',
            ]);

            if ($subscription->coupon_id) {
                $coupon = $subscription->coupon;
                $this->couponService->recordUsage($coupon,orderId:$subscription->id,userId:$payment->user_id,totalBefore: $payment->original_amount,discountValue: $payment->discount_amount);
            }

            $previousExpiredSub = UserSubscription::query()
                                        ->where('user_id', $subscription->user_id)
                                        ->where('id', '!=', $subscription->id)
                                        ->where('status', ['active', 'expired'])
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

    public function initiatePaymobPayment(Payment $payment, Request $request, string $method): string
    {
        $payment->refresh();

        if ($payment->status === 'failed') {
            $payment->update([
                'status'           => 'pending',
                'paymob_order_id'  => null,
                'failure_reason'   => null,
                'gateway_response' => null,
            ]);
            $payment->refresh();
        }

        if (is_null($payment->gateway_amount)) {
            $gatewayCurrency = $payment->gateway_currency ?? 'EGP';
            $gatewayAmount   = $payment->amount;

            if ($payment->currency === 'USD' && $gatewayCurrency === 'EGP' && !config('paymob.multi_currency_supported')) {
                $rate          = $this->exchangeRateService->usdToEgpRate();
                $gatewayAmount = round($payment->amount * $rate, 2);
            }

            $payment->update([
                'gateway_amount'   => $gatewayAmount,
                'gateway_currency' => $gatewayCurrency,
            ]);
            $payment->refresh();
        }

        if ($payment->paymob_order_id) {
            try {
                return $this->resumePaymobPayment($payment, $request, $method);
            } catch (\Exception $e) {
                Log::warning("Paymob resume failed for payment #{$payment->id}, creating new order", [
                    'error' => $e->getMessage(),
                ]);
                $payment->update(['paymob_order_id' => null]);
                $payment->refresh();
            }
        }

        $amountCents = (int) round($payment->gateway_amount * 100);
        $billingData = $this->buildBillingData($request, $payment);

        if ($method === 'card') {
            $result = $this->paymob->createCardPayment($amountCents, $billingData, "PAY-{$payment->id}-" . now()->timestamp, $payment->gateway_currency);
            $url    = $result['iframe_url'];
        } else {
            $result = $this->paymob->createWalletPayment($amountCents, $billingData, "PAY-{$payment->id}-" . now()->timestamp, $request->input('phone', ''),$payment->gateway_currency);
            $url    = $result['redirect_url'];
        }

        $payment->update(['paymob_order_id' => $result['order_id']]);

        return $url;
    }

    private function resumePaymobPayment(Payment $payment, Request $request, string $method): string
    {
        $amountCents = (int) round($payment->gateway_amount * 100);
        $billingData = $this->buildBillingData($request, $payment);

        $paymentKey = $this->paymob->getPaymentKeyForExistingOrder(
            $amountCents,
            (int) $payment->paymob_order_id,
            $billingData,
            $method,
            $payment->gateway_currency
        );

        if ($method === 'card') {
            $iframeId = config('paymob.iframe_id');
            return "https://accept.paymob.com/api/acceptance/iframes/{$iframeId}?payment_token={$paymentKey}";
        }

        return $this->paymob->payWithWalletKey($paymentKey, $request->input('phone', ''));
    }

    private function buildBillingData(Request $request, Payment $payment): array
    {
        return [
            'first_name'   => $request->input('first_name') ?: ($payment->user->name ?: 'N/A'),
            'last_name'    => $request->input('last_name')  ?: 'N/A',
            'email'        => $request->input('email')      ?: ($payment->user->email ?: 'user@example.com'),
            'phone_number' => $request->input('phone')      ?: 'N/A',
            'apartment'    => 'N/A', 'floor'    => 'N/A',
            'street'       => 'N/A', 'building' => 'N/A',
            'city'         => 'Cairo', 'country' => 'EG',
            'postal_code'  => 'N/A',  'state'   => 'N/A',
        ];
    }

    public function validateCouponForSubscription(string $code, float $amount, User $user,string $currency = 'EGP'): Coupon
    {
        $coupon = Coupon::where('code', $code)->where('status', 'active')->first();

        if (!$coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Invalid or inactive coupon.',
            ]);
        }

        $now = Carbon::now();

        if ($coupon->start_at && $now->lt($coupon->start_at)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Coupon is not active yet.',
            ]);
        }

        if ($coupon->end_at && $now->gt($coupon->end_at)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Coupon has expired.',
            ]);
        }

        if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Coupon usage limit reached.',
            ]);
        }

        $minOrderAmount = $coupon->minOrderAmountFor($currency);
        if ($minOrderAmount && $amount < $minOrderAmount) {
            throw ValidationException::withMessages(['coupon_code' => "Minimum amount is {$minOrderAmount} {$currency}."]);
        }

        if ($coupon->coupon_usages()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'You already used this coupon.',
            ]);
        }

        return $coupon;
    }

    public function calculateCouponDiscount(Coupon $coupon, float $amount, string $currency = 'EGP'): float
    {
        return $this->couponService->calculateDiscount($coupon, $amount, $currency);
    }

    public function cancelPending(User $user, int $subscriptionId): void
    {
        DB::transaction(function () use ($user, $subscriptionId) {

            $subscription = UserSubscription::query()->where('id', $subscriptionId)->where('user_id', $user->id)->where('payment_status', 'pending')
                ->where('status', 'inactive')->firstOrFail();
            Payment::where('user_subscription_id', $subscription->id)->where('status', 'pending')->delete();
            $subscription->delete();
        });
    }



    private function createPendingPayment(
        UserSubscription $subscription, User $user, float $originalAmount,
        float $discount, float $finalAmount, ?Coupon $coupon, string $currency
    ): Payment {
        $gatewayCurrency = 'EGP';
        $gatewayAmount   = $finalAmount;
        $exchangeRate    = null;

        if ($currency === 'USD' && !config('paymob.multi_currency_supported')) {
            $exchangeRate  = $this->exchangeRateService->usdToEgpRate();
            $gatewayAmount = round($finalAmount * $exchangeRate, 2);
        } elseif ($currency === 'USD') {
            $gatewayCurrency = 'USD';
        }

        return Payment::create([
            'user_id'              => $user->id,
            'user_subscription_id' => $subscription->id,
            'amount'               => $finalAmount,
            'currency'             => $currency,
            'gateway_amount'       => $gatewayAmount,
            'gateway_currency'     => $gatewayCurrency,
            'exchange_rate_used'   => $exchangeRate,
            'type'                 => 'subscription',
            'status'               => 'pending',
            'payment_gateway'      => 'paymob',
        ]);
    }

}
