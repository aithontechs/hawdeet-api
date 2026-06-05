<?php

namespace App\Http\Controllers\Application\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Notifications\PaymentSuccessNotification;
use App\Services\Cart\CartService;
use App\Services\Payment\PaymobService;
use App\Services\Purchase\BookAccessGrantService;
use App\Services\Subscription\SubscriptionService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ResponseApi;

    public function __construct(
        private PaymobService          $paymob,
        private BookAccessGrantService $grantService,
        private SubscriptionService    $subscriptionService,
        private CartService            $cartService,
    ) {}

    public function webhook(Request $request)
    {
        Log::info('Paymob Webhook', [
            'query' => $request->query(),
            'body'  => $request->all(),
        ]);

        Log::info('HMAC Fields', [
            'amount_cents'            => $obj['amount_cents'] ?? 'NULL',
            'created_at'              => $obj['created_at'] ?? 'NULL',
            'currency'                => $obj['currency'] ?? 'NULL',
            'error_occured'           => $obj['error_occured'] ?? 'NULL',
            'has_parent_transaction'  => $obj['has_parent_transaction'] ?? 'NULL',
            'id'                      => $obj['id'] ?? 'NULL',
            'integration_id'          => $obj['integration_id'] ?? 'NULL',
            'is_3d_secure'            => $obj['is_3d_secure'] ?? 'NULL',
            'is_auth'                 => $obj['is_auth'] ?? 'NULL',
            'is_capture'              => $obj['is_capture'] ?? 'NULL',
            'is_refunded'             => $obj['is_refunded'] ?? 'NULL',
            'is_standalone_payment'   => $obj['is_standalone_payment'] ?? 'NULL',
            'is_voided'               => $obj['is_voided'] ?? 'NULL',
            'order_id'                => $obj['order']['id'] ?? 'NULL',
            'owner'                   => $obj['owner'] ?? 'NULL',
            'pending'                 => $obj['pending'] ?? 'NULL',
            'success'                 => $obj['success'] ?? 'NULL',
            'source_data_pan'         => $obj['source_data']['pan'] ?? 'NULL',
            'source_data_sub_type'    => $obj['source_data']['sub_type'] ?? 'NULL',
            'source_data_type'        => $obj['source_data']['type'] ?? 'NULL',
        ]);

        $hmac = $request->query('hmac');
        $obj  = $request->input('obj');

        if (!$obj) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        if (!$this->paymob->verifyHmacFromObj($obj, $hmac)) {
            Log::warning('Paymob Webhook: Invalid HMAC');
            return response()->json(['message' => 'Invalid HMAC'], 403);
        }

        $isSuccess = (bool) ($obj['success'] ?? false);

        $merchantOrderId = $obj['order']['merchant_order_id'] ?? null;
        $paymentId = $merchantOrderId;
        if (str_contains($paymentId, 'PAY-')) {
            $paymentId = explode('-', $paymentId)[1];
        }

        $payment = Payment::find($paymentId);

        if (!$payment) {
            Log::warning('Paymob Webhook: Payment not found', ['merchant_order_id' => $merchantOrderId]);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($payment->isPaid()) {
            return response()->json(['message' => 'Already processed']);
        }

        if (!$isSuccess) {
            $payment->update([
                'status'           => 'failed',
                'failure_reason'   => $obj['data']['message'] ?? 'Payment failed',
                'gateway_response' => $obj,
            ]);
            $payment->order?->update(['payment_status' => 'failed']);
            $payment->subscription?->update(['payment_status' => 'failed']);
            Log::info("Payment #{$payment->id} failed.");
            return response()->json(['message' => 'Payment failure recorded']);
        }

        $payment->update([
            'status'                 => 'paid',
            'gateway_transaction_id' => $obj['id'],
            'paymob_order_id'        => $obj['order']['id'],
            'gateway_response'       => $obj,
            'paid_at'                => now(),
        ]);

        if ($payment->type === 'order') {
            $this->handleOrderPayment($payment);
        } elseif ($payment->type === 'subscription') {
            $this->handleSubscriptionPayment($payment);
        }

        return response()->json(['message' => 'Webhook processed successfully']);
    }

    // for test only - not used in production
    public function callback(Request $request)
    {
        return $this->successApi(message:'Payment is be paid');
        // Log::info('Paymob Webhook', [
        //     'query' => $request->query(),
        //     'body'  => $request->all(),
        // ]);

        // $hmac = $request->query('hmac');

        // if (!$this->paymob->verifyHmac($request->query(), $hmac)) {
        //     Log::warning('Paymob Webhook: Invalid HMAC');
        //     return response()->json(['message' => 'Invalid HMAC'], 403);
        // }

        // $isSuccess = filter_var($request->query('success'), FILTER_VALIDATE_BOOLEAN);

        // $paymentId = $request->query('merchant_order_id');
        // if (str_contains($paymentId, 'PAY-')) {
        //     $paymentId = explode('-', $paymentId)[1];
        // }
        // $payment   = Payment::find($paymentId);

        // if (!$payment) {
        //     Log::warning('Paymob Webhook: Payment not found', [
        //         'merchant_order_id' => $paymentId
        //     ]);
        //     return response()->json(['message' => 'Payment not found'], 404);
        // }

        // if ($payment->isPaid()) {
        //     return response()->json(['message' => 'Already processed']);
        // }

        // if (!$isSuccess) {
        //     $payment->update([
        //         'status'           => 'failed',
        //         'failure_reason'   => $request->query('data.message', 'Payment failed'),
        //         'gateway_response' => $request->query(),
        //     ]);

        //     $payment->order?->update(['payment_status' => 'failed']);
        //     $payment->subscription?->update(['payment_status' => 'failed']);

        //     Log::info("Payment #{$payment->id} failed.");
        //     return response()->json(['message' => 'Payment failure recorded']);
        // }

        // $payment->update([
        //     'status'                 => 'paid',
        //     'gateway_transaction_id' => $request->query('id'),
        //     'paymob_order_id'        => $request->query('order'),
        //     'gateway_response'       => $request->query(),
        //     'paid_at'                => now(),
        // ]);

        // if ($payment->type === 'order') {
        //     $this->handleOrderPayment($payment);
        // } elseif ($payment->type === 'subscription') {
        //     $this->handleSubscriptionPayment($payment);
        // }

        // return response()->json(['message' => 'Webhook processed successfully']);
    }

    private function handleOrderPayment(Payment $payment): void
    {
        $order = $payment->order;

        if (!$order || $order->payment_status === 'paid') return;

        $this->grantService->grantBookAccess($order);
        $this->cartService->clearCart(null, $order->user_id);
        $order->update(['payment_status' => 'paid' , 'paid_at' => now()]);
        Cache::forget("user_books:{$order->user_id}");
        $order->user->notify(new PaymentSuccessNotification(
            message: 'تم الدفع بنجاح وتم تفعيل طلبك!',
            type: 'order',
            referenceId: $order->id
        ));
        Log::info("Order #{$order->order_number} paid.");
    }


    private function handleSubscriptionPayment(Payment $payment): void
    {
        $subscription = $payment->subscription;

        if (!$subscription || $subscription->payment_status === 'paid') return;

        $this->subscriptionService->activate($payment);
        $subscription->user->notify(new PaymentSuccessNotification(
            message: 'تم تفعيل اشتراكك بنجاح!',
            type: 'subscription',
            referenceId: $subscription->id
        ));
        Log::info("Subscription #{$subscription->id} activated via Paymob webhook.");
    }
}
