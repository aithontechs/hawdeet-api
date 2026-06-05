<?php

namespace App\Services\Subscription;

use App\Models\{Payment, SubscriptionPlan, User, UserSubscription};
use App\Models\UserBook;
use App\Services\Payment\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(private PaymobService $paymob) {}

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
                'payment_gateway'      => 'paymob',

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
                'payment_gateway'      => 'paymob',

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

            $subscription = $payment->subscription ;

            $subscription->update([
                'status'         => 'active',
                'payment_status' => 'paid',
            ]);

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

        if ($payment->paymob_order_id) {
            return $this->resumePaymobPayment($payment, $request, $method);
        }

        $amountCents = (int) ($payment->amount * 100);
        $billingData = $this->buildBillingData($request, $payment);

        if ($method === 'card') {
            $result = $this->paymob->createCardPayment($amountCents,$billingData,"PAY-{$payment->id}-" . now()->timestamp);
            $url = $result['iframe_url'];
        }
        else{
            $result = $this->paymob->createWalletPayment($amountCents,$billingData,"PAY-{$payment->id}-" . now()->timestamp,$request->input('phone', ''));
            $url = $result['redirect_url'];
        }

        $payment->update(['paymob_order_id' => $result['order_id']]);
        return $url;
    }

    private function resumePaymobPayment(Payment $payment, Request $request, string $method): string
    {
        $amountCents = (int) ($payment->amount * 100);
        $billingData = $this->buildBillingData($request, $payment);

        $paymentKey = $this->paymob->getPaymentKeyForExistingOrder($amountCents,(int) $payment->paymob_order_id,$billingData,$method);

        if ($method === 'card') {
            $iframeId = config('paymob.iframe_id');
            return "https://accept.paymob.com/api/acceptance/iframes/{$iframeId}?payment_token={$paymentKey}";
        }

        return $this->paymob->payWithWalletKey($paymentKey,$request->input('phone', ''),);
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

}
