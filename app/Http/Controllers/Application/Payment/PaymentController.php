<?php

namespace App\Http\Controllers\Application\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\PaymentSuccessNotification;
use App\Services\Cart\CartService;
use App\Services\Payment\PaymobService;
use App\Services\Purchase\BookAccessGrantService;
use App\Services\Subscription\SubscriptionService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        $hmac = $request->query('hmac');
        $obj  = $request->input('obj');

        if (!$obj || !is_array($obj)) {
            Log::warning('Paymob Webhook: Missing or invalid obj payload');
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        if (!$this->paymob->verifyHmac($obj, $hmac)) {
            Log::warning('Paymob Webhook: Invalid HMAC');
            return response()->json(['message' => 'Invalid HMAC'], 403);
        }

        $merchantOrderId = data_get($obj, 'order.merchant_order_id');

        if (!$merchantOrderId) {
            Log::warning('Paymob Webhook: Missing merchant_order_id', ['obj' => $obj]);
            return response()->json(['message' => 'Invalid payload: missing merchant_order_id'], 400);
        }

        $paymentId = $this->extractPaymentId((string) $merchantOrderId);
        $payment   = Payment::find($paymentId);

        if (!$payment) {
            Log::warning('Paymob Webhook: Payment not found', ['merchant_order_id' => $merchantOrderId]);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $isSuccess = (bool) ($obj['success'] ?? false);

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

        $updated = Payment::where('id', $payment->id)
            ->where('status', '!=', 'paid')
            ->update([
                'status'                 => 'paid',
                'gateway_transaction_id' => $obj['id'],
                'paymob_order_id'        => data_get($obj, 'order.id'),
                'gateway_response'       => $obj,
                'paid_at'                => now(),
            ]);

        if (!$updated) {
            Log::info("Paymob Webhook: Payment #{$payment->id} already processed, skipping.");
            return response()->json(['message' => 'Already processed']);
        }

        $payment->refresh();

        if ($payment->type === 'order') {
            $this->handleOrderPayment($payment);
        } elseif ($payment->type === 'subscription') {
            $this->handleSubscriptionPayment($payment);
        }

        return response()->json(['message' => 'Webhook processed successfully']);
    }

    public function callback(Request $request)
    {
        $isSuccess = filter_var($request->query('success'), FILTER_VALIDATE_BOOLEAN);

        $merchantOrderId = $request->query('merchant_order_id');

        if (!$merchantOrderId) {
            return $this->errorApi(message: 'Missing merchant_order_id');
        }

        $paymentId = $this->extractPaymentId((string) $merchantOrderId);
        $payment   = Payment::find($paymentId);

        if (!$payment) {
            return $this->errorApi(message: 'Payment not found');
        }

        if ($payment->isPaid()) {
            return $this->successApi(null, message: 'Payment completed successfully');
        }

        if (!$isSuccess) {
            return $this->errorApi(message: 'Payment failed', errors: $payment->failure_reason);
        }

        return $this->successApi(null, message: 'Payment completed successfully');
    }

    private function handleOrderPayment(Payment $payment): void
    {
        $order = $payment->order;

        if (!$order || $order->payment_status === 'paid') return;

        DB::transaction(function () use ($order) {
            $this->deductPhysicalStock($order);
            $this->grantService->grantBookAccess($order);
            $this->cartService->clearCart(null, $order->user_id);
            $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
            Cache::forget("user_books:{$order->user_id}");
        });

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

        DB::transaction(function () use ($payment) {
            $this->subscriptionService->activate($payment);
        });

        $subscription->user->notify(new PaymentSuccessNotification(
            message: 'تم تفعيل اشتراكك بنجاح!',
            type: 'subscription',
            referenceId: $subscription->id
        ));

        Log::info("Subscription #{$subscription->id} activated via Paymob webhook.");
    }


    private function extractPaymentId(string $merchantOrderId): string
    {
        return str_contains($merchantOrderId, 'PAY-')
            ? explode('-', $merchantOrderId)[1]
            : $merchantOrderId;
    }

    private function deductPhysicalStock(Order $order): void
    {
        $physicalItems = $order->items()->where('item_type', 'physical')->get();

        foreach ($physicalItems as $item) {
            $column = $item->cover_type === 'hard_cover'
                ? 'physical_hard_cover_stock'
                : 'physical_stock';

            $affected = DB::table('books')
                ->where('id', $item->book_id)
                ->where($column, '>=', $item->quantity)
                ->decrement($column, $item->quantity);

            if (!$affected) {
                DB::table('books')->where('id', $item->book_id)->decrement($column, 0);

                Log::critical("OVERSELL: Stock deduction failed for paid order. Manual intervention required.", [
                    'order_id'   => $order->id,
                    'book_id'    => $item->book_id,
                    'cover_type' => $item->cover_type,
                    'quantity'   => $item->quantity,
                ]);


            }
        }
    }
}
