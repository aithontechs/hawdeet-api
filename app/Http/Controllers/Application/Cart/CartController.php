<?php

namespace App\Http\Controllers\Application\Cart;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Services\Currency\CurrencyResolver;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ResponseApi ;
    public function __construct(
        private CartService        $cartService,
        private CouponService $couponService,
        private CurrencyResolver $currencyResolver,
    ) {}

    public function index(Request $request)
    {
        $cookieId = $this->cartService->getOrCreateCookieId($request);
        $user     = auth('user-api')->user();
        $currency = $this->currencyResolver->resolve($request);


        $items = $this->cartService->getItems($cookieId, $user , $currency);
        $total = $this->cartService->getTotal($cookieId, $user, $currency);

        return $this->successApi([
                'items'      => $items,
                'total'      => $total,
                'currency'    => $currency,
                'item_count' => $items->count(),
                'is_guest'   => is_null($user),
                'guest_token' => $cookieId,
            ],'Carts fetched successfully') ;
    }

    public function store(Request $request)
    {
        $request->validate([
            'book_id' => 'required|integer|exists:books,id',
            'item_type' => 'required|in:digital,physical',
            'quantity'  => 'integer|min:1|max:99',
            'cover_type' => 'required_if:item_type,physical|nullable|in:normal,hard_cover',
        ]);
        $cookieId = $this->cartService->getOrCreateCookieId($request);
        $user     = auth('user-api')->user() ;
        $cartItem = $this->cartService->addItem(cookieId: $cookieId,bookId: $request->book_id,itemType: $request->input('item_type', 'digital'),quantity: $request->input('quantity', 1),user:$user , coverType: $request->input('cover_type'));
        return $this->successApi($cartItem, 'Cart added successfully' , 201);
    }

    public function destroy(Request $request , $cartId)
    {
        $user = auth('user-api')->user();
        $cookieId = $this->cartService->getOrCreateCookieId($request);
        $removed = $this->cartService->removeItem($cartId, $cookieId, $user);
        if (! $removed) {
            return $this->errorApi('Unauthorized or item not found', 403);
        }
        return $this->successApi(null, 'Cart removed successfully');
    }

    // preview coupons only without applying
    // public function applyCoupon(Request $request)
    // {
    //     $request->validate([
    //         'coupon_code' => 'required|string',
    //     ]);

    //     $user     = auth('user-api')->user();
    //     $cookieId = $this->cartService->getOrCreateCookieId($request);
    //     $total    = $this->cartService->getTotal($cookieId, $user);

    //     $coupon   = $this->couponService->validate($request->coupon_code, $total, $user );
    //     $discount = $this->couponService->calculateDiscount($coupon, $total);

    //     return $this->successApi([
    //         'original_total'  => $total,
    //         'discount'        => $discount,
    //         'final_total'     => $total - $discount,
    //         'coupon_code'     => $coupon->code,
    //     ], 'Coupon applied successfully');
    // }

    public function updateQuantity(Request $request, $cartId)
    {
        $request->validate([
            'action' => 'required|in:increment,decrement',
        ]);

        $user     = auth('user-api')->user();
        $cookieId = $this->cartService->getOrCreateCookieId($request);
        $currency = $this->currencyResolver->resolve($request);

        $result = $this->cartService->updateQuantity(
            cartId:   $cartId,
            action:   $request->action,
            cookieId: $cookieId,
            user:     $user,
            currency: $currency,
        );

        $message = $result['removed'] ? 'Item removed from cart' : 'Quantity updated successfully';
        return $this->successApi($result, $message);
    }

    public function updateItems(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.cart_id' => 'required|integer|exists:carts,id',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        $user = auth('user-api')->user();
        $cookieId = $this->cartService->getOrCreateCookieId($request);
        $currency = $this->currencyResolver->resolve($request);

        $this->cartService->updateItemsQuantity(items: $request->items,cookieId: $cookieId,user: $user);

        $items = $this->cartService->getItems($cookieId, $user , $currency);
        $total = $this->cartService->getTotal($cookieId, $user , $currency);

        return $this->successApi([
            'items'      => $items,
            'total'      => $total,
            'currency'   => $currency,
            'item_count' => $items->count(),
        ], 'Cart updated successfully');
    }

    public function updateAll(Request $request)
    {
        $request->validate([
            'action' => 'required|in:increment,decrement',
        ]);

        $user = auth('user-api')->user();
        $cookieId = $this->cartService->getOrCreateCookieId($request);

        $result = $this->cartService->updateAllItemsQuantity(
            $request->action,
            $cookieId,
            $user
        );

        return $this->successApi(['updated_items' => $result], 'Cart updated successfully');
    }

}
