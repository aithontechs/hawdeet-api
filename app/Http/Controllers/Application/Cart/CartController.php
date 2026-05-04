<?php

namespace App\Http\Controllers\Application\Cart;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Services\Purchase\BookAccessGrantService;
use App\Services\Purchase\BookPurchaseService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CartController extends Controller
{
    use ResponseApi ;
    public function __construct(
        private CartService        $cartService,
        private BookPurchaseService $purchaseService,
        private BookAccessGrantService $bookAccessGrantService,
        private CouponService $couponService
    ) {}

    public function index(Request $request)
    {
        $cookieId = $this->cartService->getOrCreateCookieId();
        $user     = auth('user-api')->user();

        $items = $this->cartService->getItems($cookieId, $user);
        $total = $this->cartService->getTotal($cookieId, $user);

        return $this->successApi([
                'items'      => $items,
                'total'      => $total,
                'item_count' => $items->count(),
                'is_guest'   => is_null($user),
            ],'Carts fetched successfully') ;
    }

    public function store(Request $request)
    {
        $request->validate([
            'book_id' => 'required|integer|exists:books,id',
        ]);
        $cookieId = $this->cartService->getOrCreateCookieId();
        $user     = auth('user-api')->user() ;
        $cartItem = $this->cartService->addItem(cookieId: $cookieId,bookId: $request->book_id,user:$user);
        return $this->successApi($cartItem , 'Cart added successfully' , 201);
    }

    public function destroy($cartId)
    {
        $user = auth('user-api')->user();
        $cookieId = $this->cartService->getOrCreateCookieId();
        $removed = $this->cartService->removeItem($cartId, $cookieId, $user);
        if (! $removed) {
            return $this->errorApi('Unauthorized or item not found', 403);
        }
        return $this->successApi(null, 'Cart removed successfully');
    }

    public function applyCoupon(Request $request)
    {
        $request->validate([
            'coupon_code' => 'required|string',
        ]);

        $user     = auth('user-api')->user();
        $cookieId = $this->cartService->getOrCreateCookieId();
        $total    = $this->cartService->getTotal($cookieId, $user);

        $coupon   = $this->couponService->validate($request->coupon_code, $total, $user);
        $discount = $this->couponService->calculateDiscount($coupon, $total);

        return $this->successApi([
            'original_total'  => $total,
            'discount'        => $discount,
            'final_total'     => $total - $discount,
            'coupon_code'     => $coupon->code,
        ], 'Coupon applied successfully');
    }

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
