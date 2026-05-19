<?php

namespace App\Http\Controllers\Application\Cart;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ResponseApi ;
    public function __construct(
        private CartService        $cartService,
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
            'item_type' => 'required|in:digital,physical',
            'quantity'  => 'integer|min:1|max:99',
        ]);
        $cookieId = $this->cartService->getOrCreateCookieId();
        $user     = auth('user-api')->user() ;
        $cartItem = $this->cartService->addItem(cookieId: $cookieId,bookId: $request->book_id,itemType: $request->input('item_type', 'digital'),quantity: $request->input('quantity', 1),user:$user);
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

    // preview coupons only without applying
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

}
