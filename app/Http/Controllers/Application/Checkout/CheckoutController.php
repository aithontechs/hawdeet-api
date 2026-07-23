<?php

namespace App\Http\Controllers\Application\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Checkout\CheckoutRequest;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Services\Currency\CurrencyResolver;
use App\Services\Purchase\BookAccessGrantService;
use App\Services\Purchase\BookPurchaseService;
use App\Services\Shipping\ShippingService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    use ResponseApi;

    public function __construct(
        private CartService $cartService,
        private BookPurchaseService    $purchaseService,
        private BookAccessGrantService $grantService,
        private CouponService  $couponService,
        private ShippingService $shippingService,
        private CurrencyResolver  $currencyResolver,
    ) {}

    public function shippingZones(Request $request)
    {
        return $this->successApi(
            $this->shippingService->getZones($request),
            'Shipping zones fetched successfully'
        );
    }

    public function preview(Request $request)
    {
        $request->validate([
            'shipping_zone_id' => 'nullable|exists:shipping_zones,id',
            'coupon_code'      => 'nullable|string',
        ]);

        $user     = auth('user-api')->user();
        $cookieId = $this->cartService->getOrCreateCookieId($request);
        $currency = $this->currencyResolver->resolve($request);
        $items   = $this->cartService->getItems($cookieId, $user, $currency);

        if ($items->isEmpty()) {
            return $this->errorApi('Cart is empty', 422);
        }

        $hasPhysical  = $items->contains(fn ($i) => $i['item_type'] === 'physical');
        $subtotal     = $this->cartService->getTotal($cookieId, $user , $currency);
        $shippingCost = 0;
        $shippingZone = null;
        $usedAddress  = null;

        if ($hasPhysical) {
            if ($request->filled('shipping_zone_id')) {
                $shippingZone = $this->shippingService->getZone($request , $request->shipping_zone_id);
            } else {
                $defaultAddress = $user->shippingAddresses()
                    ->with('zone')
                    ->where('is_default', true)
                    ->first();

                    if ($defaultAddress?->zone) {
                        $shippingZone = $defaultAddress->zone;
                        $usedAddress  = [
                            'id'             => $defaultAddress->id,
                            'recipient_name' => $defaultAddress->recipient_name,
                            'address_line'   => $defaultAddress->address_line,
                            'city'           => $defaultAddress->city,
                        ];
                    } else {
                        $shippingZone = $this->shippingService->getDefaultZone($request);
                    }
            }

            $shippingCost = $currency === 'USD'
                ? (float) ($shippingZone?->cost_usd ?? $shippingZone?->cost ?? 0)
                : (float) ($shippingZone?->cost ?? 0);
        }

        $discount      = 0;
        $couponPreview = null;

        if ($request->filled('coupon_code')) {
            $coupon        = $this->couponService->validate($request->coupon_code, $subtotal, $user , $currency);
            $discount      = $this->couponService->calculateDiscount($coupon, $subtotal , $currency);
            $couponPreview = [
                'code'     => $coupon->code,
                'discount' => $discount,
                'currency' => $currency,
            ];
        }

        $total = $subtotal + $shippingCost - $discount;

        return $this->successApi([
            'items'        => $items,
            'subtotal'     => $subtotal,
            'currency'     => $currency,
            'shipping'     => [
                'cost'         => $shippingCost,
                'zone'         => $shippingZone ? [
                    'id'       => $shippingZone->id,
                    'name'     => $shippingZone->name,
                    'days_min' => $shippingZone->days_min,
                    'days_max' => $shippingZone->days_max,
                ] : null,
                'address'      => $usedAddress,
            ],
            'coupon'       => $couponPreview,
            'discount'     => $discount,
            'total'        => $total,
            'has_physical' => $hasPhysical,
        ], 'Order preview');
    }

    public function checkout(CheckoutRequest $request)
    {
        $user     = auth('user-api')->user();
        $cookieId = $this->cartService->getOrCreateCookieId($request);
        $currency = $this->currencyResolver->resolve($request);
        $items    = $this->cartService->getItems($cookieId, $user , $currency);

        if ($items->isEmpty()) {
            return $this->errorApi('Cart is empty', 422);
        }

        $hasPhysical       = $items->contains(fn ($i) => $i['item_type'] === 'physical');
        $shippingCost      = 0;
        $shippingAddressId = null;

        if ($hasPhysical) {
            $address = $request->filled('shipping_address_id')
                ? $user->shippingAddresses()->with('zone')->find($request->shipping_address_id)
                : $user->shippingAddresses()->with('zone')->where('is_default', true)->first();

            abort_unless($address, 422, 'Shipping address is required for physical books.');
            abort_unless($address->zone?->is_active, 422, 'Shipping zone is not available.');

            abort_unless($address->user_id === $user->id, 403, 'Unauthorized address.');

            $shippingCost = $currency === 'USD' ? (float) ($address->zone->cost_usd ?? $address->zone->cost) : (float) $address->zone->cost;
            $shippingAddressId = $address->id;
        }

        $subtotal = $this->cartService->getTotal($cookieId, $user , $currency);
        $discount = 0;
        $coupon   = null;

        if ($request->filled('coupon_code')) {
            $coupon   = $this->couponService->validate($request->coupon_code, $subtotal, $user , $currency);
            $discount = $this->couponService->calculateDiscount($coupon, $subtotal , $currency);
        }

        $total = $subtotal + $shippingCost - $discount;

        $idempotencyKey = $this->purchaseService->buildIdempotencyKey(
            $user->id, $items, $subtotal, $shippingCost, $discount , $currency
        );

        $existingOrder = Order::where('idempotency_key', $idempotencyKey)
                            ->where('user_id', $user->id)
                            ->first();

        if ($existingOrder) {
            if ($existingOrder->payment_status === 'paid') {
                return $this->errorApi('This order has already been paid.', 422);
            }

            $existingPayment = $existingOrder->payments()
                ->where('status', 'pending')
                ->where('currency', $currency)
                ->latest()
                ->first();

            if (!$existingPayment) {
                $existingPayment = $this->purchaseService->createPendingPayment(
                    $existingOrder, $user, $request->payment_method
                );
            }

            $paymentUrl = $this->purchaseService->initiatePaymobPayment(
                $existingPayment, $request, $request->payment_method
            );

            return $this->successApi([
                'order_number' => $existingOrder->order_number,
                'subtotal'     => $subtotal,
                'shipping'     => $shippingCost,
                'discount'     => $discount,
                'total'        => $total,
                'payment_url'  => $paymentUrl,
                'status'       => 'pending',
                'resumed'      => true,
            ], 'Resuming existing order');
        }

        $order = $this->purchaseService->purchase(
            user:              $user,
            items:             $items,
            subtotal:          $subtotal,
            shippingCost:      $shippingCost,
            discount:          $discount,
            total:             $total,
            paymentMethod:     $request->payment_method,
            currency:          $currency,
            shippingAddressId: $shippingAddressId,
            idempotencyKey:    $idempotencyKey,
        );

        if ($coupon) {
            $this->couponService->recordUsage($coupon, $order->id, $user->id, $subtotal, $discount);
        }

        $payment    = $this->purchaseService->createPendingPayment($order, $user, $request->payment_method);
        $paymentUrl = $this->purchaseService->initiatePaymobPayment($payment, $request, $request->payment_method);

        return $this->successApi([
            'order_number'=> $order->order_number,
            'subtotal'    => $subtotal,
            'shipping'    => $shippingCost,
            'discount'    => $discount,
            'total'       => $total,
            'currency'    => $currency,
            'payment_url' => $paymentUrl,
            'status'      => 'pending',
            'resumed'     => false,
        ], 'Proceed to payment');
    }
}
