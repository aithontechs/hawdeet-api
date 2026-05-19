<?php

namespace App\Http\Controllers\Application\Checkout;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Services\Purchase\BookAccessGrantService;
use App\Services\Purchase\BookPurchaseService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckoutController extends Controller
{
    use ResponseApi ;
    public function __construct(
        private CartService        $cartService,
        private BookPurchaseService $purchaseService,
        private BookAccessGrantService $bookAccessGrantService,
        private CouponService $couponService
    ) {}

    public function checkout(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|in:wallet,card,cash',
        ]);

        $user     = auth('user-api')->user() ;
        $cookieId = $this->cartService->getOrCreateCookieId();
        $items    = $this->cartService->getItems($cookieId, $user);


        if ($items->isEmpty()) {
            return $this->errorApi('Cart is empty' , 422) ;
        }

        // $hasPhysical = $items->contains(fn ($i) => $i['item_type'] === 'physical');

        // if ($hasPhysical && !$request->filled('shipping_address')) {
        //     return $this->errorApi('Shipping address is required for physical books.', 422);
        // }

        $totalBefore  = $this->cartService->getTotal($cookieId, $user);
        $discount     = 0;
        $coupon       = null;

        if($request->filled('coupon_code'))
        {
            $coupon = $this->couponService->validate($request->coupon_code, $totalBefore, $user);
            $discount = $this->couponService->calculateDiscount($coupon, $totalBefore);
        }

        $finalTotal = $totalBefore - $discount;

        $bookIds = $items->pluck('book_id')->toArray();
        $order = $this->purchaseService->purchase($user, $bookIds , $finalTotal , $discount , $totalBefore);
        // $order = $this->purchaseService->pay($order, $request->payment_method);
        if ($coupon)
        {
            $this->couponService->recordUsage($coupon, $order->id, $user->id, $totalBefore, $discount);
        }

        $this->bookAccessGrantService->grantBookAccess($order);
        $this->cartService->clearCart($cookieId, $user->id);
        Cache::forget("user_books:{$user->id}");
        return $this->successApi([
            'order_number'  => $order->order_number,
            'total_before'  => $totalBefore,
            'discount'      => $discount,
            'final_total'   => $finalTotal,
            'books_count'   => count($bookIds),
        ], 'The books are open now');

    }
}
