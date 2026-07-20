<?php

namespace App\Services\Purchase;

use App\Models\Admin;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PhysicalOrder;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Notifications\NewOrderCreated;
use App\Services\Currency\ExchangeRateService;
use App\Services\Payment\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class BookPurchaseService
{
    public function __construct(private PaymobService $paymob ,private ExchangeRateService $exchangeRateService,
) {}

    public function purchase(User $user, Collection $items,float $subtotal,float $shippingCost,float $discount,float $total,string $paymentMethod, string $currency = 'EGP', ?int $shippingAddressId = null, ?string $idempotencyKey = null,): Order
    {
        return DB::transaction(function () use (
            $user, $items, $subtotal, $shippingCost,
            $discount, $total, $paymentMethod, $currency , $shippingAddressId , $idempotencyKey
        ) {
            $this->validateItems($user, $items);

            $hasPhysical = $items->contains(fn ($i) => $i['item_type'] === 'physical');

            $order = Order::create([
                        'user_id' => $user->id,
                        'order_number' => Order::generateOrderNumber(),
                        'idempotency_key'  => $idempotencyKey,
                        'subtotal' => $subtotal,
                        'shipping_cost'=> $shippingCost,
                        'discount' => $discount,
                        'total' => $total,
                        'currency'        => $currency,
                        'has_physical'   => $hasPhysical,
                        'payment_method' => $paymentMethod,
                        'payment_status' => 'pending',
                    ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id'  => $item['book_id'],
                    'book_name'=> $item['title'],
                    'price'    => $item['unit_price'],
                    'item_type'=> $item['item_type'],
                    'cover_type' => $item['item_type'] === 'physical' ? $item['cover_type'] : null,
                    'quantity' => $item['quantity'],
                    'access_duration_days' => $item['item_type'] === 'digital' ? 365 : null,
                ]);
            }

            if ($hasPhysical && $shippingAddressId) {
                $address = ShippingAddress::with('zone')->findOrFail($shippingAddressId);

                PhysicalOrder::create([
                    'order_id'           => $order->id,
                    'shipping_address_id'=> $shippingAddressId,
                    'shipping_cost'      => $shippingCost,
                    'delivery_status'    => 'pending',
                ]);
            }
            Notification::send(Admin::active()->get(), new NewOrderCreated($user));
            return $order->load('items');
        });
    }

    public function createPendingPayment(Order $order, User $user, string $method): Payment
    {
        $existing = Payment::where('order_id', $order->id)
            ->whereIn('status',  ['pending', 'failed'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $gatewayCurrency = 'EGP';
        $gatewayAmount   = $order->total;
        $exchangeRate    = null;

        if ($order->currency === 'USD' && !config('paymob.multi_currency_supported')) {
            $exchangeRate    = $this->exchangeRateService->usdToEgpRate();
            $gatewayAmount   = round($order->total * $exchangeRate, 2);
        } elseif ($order->currency === 'USD') {
            $gatewayCurrency = 'USD';
        }

        return Payment::create([
            'user_id'         => $user->id,
            'order_id'        => $order->id,
            'amount'          => $order->total,
            'currency'          => $order->currency,
            'gateway_amount'     => $gatewayAmount,
            'gateway_currency'   => $gatewayCurrency,
            'exchange_rate_used' => $exchangeRate,
            'type'            => 'order',
            'status'          => 'pending',
            'payment_gateway' => 'paymob',
        ]);
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

        // $amountCents = (int) ($payment->amount * 100);
        $amountCents = (int) ($payment->gateway_amount * 100);

        $billingData = [
            'first_name'   => $request->input('first_name', $payment->user->name),
            'last_name'    => $request->input('last_name', '.'),
            'email'        => $request->input('email', $payment->user->email ?? 'user@example.com'),
            'phone_number' => $request->input('phone', 'N/A'),
            'apartment'    => 'N/A', 'floor'    => 'N/A',
            'street'       => 'N/A', 'building' => 'N/A',
            'city'         => 'Cairo', 'country' => 'EG',
            'postal_code'  => 'N/A',  'state'   => 'N/A',
        ];

        if ($method === 'card') {

            $result = $this->paymob->createCardPayment($amountCents,$billingData,"PAY-{$payment->id}-" . now()->timestamp,$payment->gateway_currency);
            $url = $result['iframe_url'];
        } else {

            $result = $this->paymob->createWalletPayment($amountCents,$billingData,"PAY-{$payment->id}-" . now()->timestamp,$request->input('phone', '') , $payment->gateway_currency);
            $url = $result['redirect_url'];
        }

        $payment->update(['paymob_order_id' => $result['order_id']]);

        return $url;
    }

    private function validateItems(User $user, Collection $items): void
    {
        foreach ($items as $item) {
            if ($item['item_type'] === 'digital') {
                $owned = $user->userBooks()
                    ->where('book_id', $item['book_id'])
                    ->active()->exists();

                if ($owned) {
                    throw ValidationException::withMessages([
                        'books' => "You already own \"{$item['title']}\".",
                    ]);
                }
            }

            if ($item['item_type'] === 'physical') {
                $book = Book::find($item['book_id']);
                $coverType = $item['cover_type'] ?? 'normal';
                if (!$book || $book->physicalStockFor($coverType) < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'books' => "Not enough stock for \"{$item['title']}\".",
                    ]);
                }
            }
        }
    }


    public function buildIdempotencyKey(int $userId, Collection $items, float $subtotal, float $shippingCost, float $discount , string $currency = 'EGP'): string
    {
        $itemsSignature = $items
            ->sortBy('book_id')
            ->map(fn ($i) => "{$i['book_id']}:{$i['item_type']}:{$i['cover_type']}:{$i['quantity']}")
            ->join(',');

        $cartIds = $items->pluck('cart_id')->sort()->join(',');

        return md5("{$userId}|{$itemsSignature}|{$cartIds}|{$subtotal}|{$shippingCost}|{$discount}|{$currency}");
    }


    private function resumePaymobPayment(Payment $payment, Request $request, string $method): string
    {
        $amountCents = (int) ($payment->amount * 100);
        $billingData = $this->buildBillingData($request, $payment);

        $paymentKey = $this->paymob->getPaymentKeyForExistingOrder(
            $amountCents,
            (int) $payment->paymob_order_id,
            $billingData,
            $method ,
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

}
