<?php

namespace App\Services\Cart;

use App\Models\Book;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{

    public function getOrCreateCookieId(Request $request): ?string
    {
        if(auth('user-api')->user()){
            return null ;
        }
        $cookieId = $request->header('X-Guest-Token')
                    ?? $request->input('guest_token')
                    ?? Cookie::get('cookie_id');
        if(! $cookieId){
            $cookieId = (string) Str::uuid() ;
            Cookie::queue('cookie_id' , $cookieId  , 60 * 24 * 30 ) ;
        }
        return $cookieId ;
    }

    public function addItem(?string $cookieId, int $bookId, string $itemType = 'digital', int $quantity = 1, ?User $user = null): Cart
    {
        $book = Book::where('id', $bookId)->where('published', 1)->firstOrFail();

        $this->validateItemType($book, $itemType);

        if ($itemType === 'physical') {
            $this->validateStock($book, $quantity);
        }

        if ($user) {
            if ($itemType === 'digital') {
                $alreadyOwned = $user->userBooks()->where('book_id', $bookId)->exists();
                if ($alreadyOwned) {
                    throw ValidationException::withMessages([
                        'book' => 'You already own the digital version of this book.',
                    ]);
                }
            }

            $cartItem = Cart::firstOrCreate(
                [
                    'user_id'   => $user->id,
                    'book_id'   => $bookId,
                    'item_type' => $itemType,
                ],
                ['quantity' => $itemType === 'physical' ? $quantity : 1]
            );
        } else {
            $cartItem = Cart::firstOrCreate(
                [
                    'cookie_id' => $cookieId,
                    'book_id'   => $bookId,
                    'item_type' => $itemType,
                ],
                ['quantity' => $itemType === 'physical' ? $quantity : 1]
            );
        }

        if (!$cartItem->wasRecentlyCreated && $itemType === 'physical') {
            $newQuantity = $cartItem->quantity + $quantity;

            if ($newQuantity > $book->physical_stock) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot add {$quantity} more. Only {$book->physical_stock} in stock and you already have {$cartItem->quantity} in cart.",
                ]);
            }

            $cartItem->increment('quantity', $quantity);
            $cartItem->refresh();
        }

        return $cartItem;
    }

    public function removeItem(int $cartId, string $cookieId = null , ?User $user = null): bool
    {
        $query = Cart::where('id', $cartId);
        $user ? $query->where('user_id', $user->id) : $query->where('cookie_id', $cookieId) ;
        return $query->delete() > 0;
    }


    public function getItems(?string $cookieId, ?User $user)
    {
        return Cart::forSession($cookieId, $user?->id)
            ->with(['book:id,title,price,physical_price,is_subscription_included,cover,type,author_id' , 'book.author:id,name'])

            ->get()
            ->map(function (Cart $item) use ($user) {
                $price = $item->item_type === 'digital'
                    ? $item->book->price
                    : $item->book->physical_price;

                return [
                    'cart_id'         => $item->id,
                    'book_id'         => $item->book_id,
                    'book_cover'      => $item->book->cover_url,
                    'title'           => $item->book->title,
                    'item_type'       => $item->item_type,
                    'quantity'        => $item->quantity,
                    'unit_price'      => $price,
                    'total_price'     => $price * $item->quantity,
                    'in_subscription' => $item->item_type === 'digital'
                        && $user?->hasActiveSubscription()
                        && $item->book->is_subscription_included,
                    'author' => $item->book->author?->name
                ];
            });
    }


    public function getTotal(?string $cookieId, ?User $user): float
    {
        return Cart::forSession($cookieId, $user?->id)
                ->with('book:id,price,physical_price,type')
                ->get()
                ->sum(function (Cart $item) {
                    $price = $item->item_type === 'digital'
                        ? $item->book->price
                        : $item->book->physical_price;

                    return $price * $item->quantity;
                });
    }

    public function clearCart(?string $cookieId, ?int $userId = null): void
    {
        Cart::forSession($cookieId, $userId)->delete();
    }


    private function validateItemType(Book $book, string $itemType): void
    {
        $valid = match($book->type) {
            'digital'  => $itemType === 'digital',
            'physical' => $itemType === 'physical',
            'both'     => in_array($itemType, ['digital', 'physical']),
        };

        if (!$valid) {
            throw ValidationException::withMessages([
                'item_type' => "This book is not available as {$itemType}.",
            ]);
        }
    }

    private function validateStock(Book $book, int $quantity): void
    {
        if ($book->physical_stock <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'This book is out of stock.',
            ]);
        }

        if ($quantity > $book->physical_stock) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$book->physical_stock} copies available.",
            ]);
        }
    }

    public function updateQuantity(int $cartId, string $action, ?string $cookieId, ?User $user): array
    {
        $query = Cart::where('id', $cartId)->where('item_type', 'physical');
        $user
            ? $query->where('user_id', $user->id)
            : $query->where('cookie_id', $cookieId);

        $cartItem = $query->with('book:id,title,price,physical_price,physical_stock,cover')->firstOrFail();

        if ($action === 'increment') {
            if ($cartItem->quantity >= $cartItem->book->physical_stock) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot add more. Only {$cartItem->book->physical_stock} in stock.",
                ]);
            }
            $cartItem->increment('quantity');

        } elseif ($action === 'decrement') {
            if ($cartItem->quantity <= 1) {
                $cartItem->delete();
                return ['removed' => true, 'cart_id' => $cartId];
            }
            $cartItem->decrement('quantity');
        }

        $cartItem->refresh();

        return [
            'removed'     => false,
            'cart_id'     => $cartItem->id,
            'book_id'     => $cartItem->book_id,
            'title'       => $cartItem->book->title,
            'item_type'   => $cartItem->item_type,
            'quantity'    => $cartItem->quantity,
            'unit_price'  => $cartItem->book->physical_price,
            'total_price' => $cartItem->book->physical_price * $cartItem->quantity,
        ];
    }

    public function mergeGuestCart(string $cookieId, User $user): void
    {
        $guestItems = Cart::where('cookie_id', $cookieId)->with('book')->get();

        if ($guestItems->isEmpty()) return;

        foreach ($guestItems as $guestItem) {
            $book = $guestItem->book;
            if (!$book || !$book->published) continue;

            if ($guestItem->item_type === 'digital') {
                $alreadyOwned = $user->userBooks()->where('book_id', $guestItem->book_id)->exists();
                if ($alreadyOwned) continue;
            }

            $existingItem = Cart::where('user_id', $user->id)
                ->where('book_id', $guestItem->book_id)
                ->where('item_type', $guestItem->item_type)
                ->first();

            if ($existingItem) {
                if ($guestItem->item_type === 'physical') {
                    $newQuantity = $existingItem->quantity + $guestItem->quantity;
                    $finalQuantity = min($newQuantity, $book->physical_stock);
                    $existingItem->update(['quantity' => $finalQuantity]);
                }
            } else {
                $guestItem->update([
                    'user_id'   => $user->id,
                    'cookie_id' => null,
                ]);
                continue;
            }

            $guestItem->delete();
        }
    }

    public function updateItemsQuantity(array $items,?string $cookieId,?User $user): array
    {
        $results = [];
        DB::transaction(function () use ($items, $cookieId, $user, &$results) {
            foreach ($items as $item) {
                $cartItemQuery = Cart::where('id', $item['cart_id']);

                $user
                    ? $cartItemQuery->where('user_id', $user->id)
                    : $cartItemQuery->where('cookie_id', $cookieId);

                $cartItem = $cartItemQuery
                    ->with('book:id,physical_stock,physical_price')
                    ->first();

                if (
                    ! $cartItem ||
                    $cartItem->item_type !== 'physical'
                ) {
                    continue;
                }

                $quantity = (int) $item['quantity'];

                if ($quantity < 1) {
                    $cartItem->delete();

                    $results[] = [
                        'cart_id' => $item['cart_id'],
                        'removed' => true,
                    ];

                    continue;
                }

                if ($quantity > $cartItem->book->physical_stock) {
                    throw ValidationException::withMessages([
                        'quantity' => "Only {$cartItem->book->physical_stock} available for book id = {$cartItem->book_id}.",
                    ]);
                }

                $cartItem->update([
                    'quantity' => $quantity
                ]);

                $results[] = [
                    'cart_id'   => $cartItem->id,
                    'updated'   => true,
                    'quantity'  => $cartItem->quantity,
                ];
            }
        });

        return $results;
    }

    public function updateAllItemsQuantity(string $action,?string $cookieId,?User $user): array
    {

        $query = Cart::where('item_type', 'physical');

        $user? $query->where('user_id', $user->id) : $query->where('cookie_id', $cookieId);
        $items = $query->with('book:id,physical_stock,physical_price')->get();

        $results = [];

        DB::transaction(function () use ($items, $action, &$results) {

            foreach ($items as $cartItem) {

                if ($action === 'increment') {

                    if ($cartItem->quantity < $cartItem->book->physical_stock) {
                        $cartItem->increment('quantity');

                        $results[] = [
                            'cart_id' => $cartItem->id,
                            'quantity' => $cartItem->quantity + 1,
                            'action'   => 'increment'
                        ];
                    }

                } elseif ($action === 'decrement') {

                    if ($cartItem->quantity <= 1) {
                        $cartItem->delete();

                        $results[] = [
                            'cart_id' => $cartItem->id,
                            'removed' => true
                        ];
                    } else {
                        $cartItem->decrement('quantity');

                        $results[] = [
                            'cart_id' => $cartItem->id,
                            'quantity' => $cartItem->quantity - 1,
                            'action'   => 'decrement'
                        ];
                    }
                }
            }
        });

        return $results;
    }
}
