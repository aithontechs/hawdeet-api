<?php

namespace App\Services\Cart;

use App\Models\Book;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{

    public function getOrCreateCookieId()
    {
        if(auth('user-api')->user()){
            return ;
        }
        $cookieId = Cookie::get('cookie_id') ;
        if(! $cookieId){
            $cookieId = (string) Str::uuid() ;
            Cookie::queue('cookie_id' , $cookieId  , 60 * 24 * 30 ) ;
        }
        return $cookieId ;
    }

    public function addItem(?string $cookieId = null  , int $bookId, ?User $user = null)
    {
        $book = Book::where('id' , $bookId)->where('published' , 1)->first();
        if(!$book){
            throw ValidationException::withMessages([
                'book' => 'the book is not valid',
            ]);
        }

        if ($user) {
            $alreadyOwned = $user->userBooks()->where('book_id', $bookId)->exists(); // لازم يكون active

            if ($alreadyOwned) {
                throw ValidationException::withMessages([
                    'book' => 'You already have this book',
                ]);
            }

            return Cart::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'book_id' => $bookId,
                ]
            );
        }

        return Cart::firstOrCreate(
            [
                'cookie_id' => $cookieId,
                'book_id'   => $bookId,
            ]
        );
    }

    public function removeItem(int $cartId, string $cookieId = null , ?User $user = null): bool
    {
        $query = Cart::where('id', $cartId);
        $user ? $query->where('user_id', $user->id) : $query->where('cookie_id', $cookieId) ;
        return $query->delete() > 0;
    }


    public function getItems(?string $cookieId = NULL, ?User $user = null): Collection
    {
        return Cart::forSession($cookieId, $user?->id)
                    ->with('book:id,title,description,price,is_subscription_included,cover')
                    ->get()
                    ->map(function (Cart $item) use ($user) {
                        return [
                            'cart_id' => $item->id,
                            'book_id' => $item->book_id,
                            'book_cover' => $item->book->cover_url ,
                            'title'=> $item->book->title,
                            'price'    => $item->book->price,
                            'in_subscription'=> $user?->hasActiveSubscription() && $item->book->is_subscription_included,
                        ];
                    });
    }


    public function getTotal(?string $cookieId, ?User $user = null)
    {
        $cartsIds = Cart::forSession($cookieId, $user?->id)->pluck('book_id');
        $total_price = Book::whereIn('id' , $cartsIds)->sum('price') ;
        return $total_price ;
    }

    public function clearCart(?string $cookieId, ?int $userId = null): void
    {
        Cart::forSession($cookieId, $userId)->delete();
    }

    public function mergeGuestCart(string $cookieId, User $user): array
    {
        $merged  = 0;
        $skipped = 0;

        return DB::transaction(function () use ($cookieId, $user, &$merged, &$skipped) {
            $guestItems = Cart::where('cookie_id', $cookieId)->whereNull('user_id')->get();

            foreach ($guestItems as $guestItem) {
                $existsInUserCart = Cart::where('user_id', $user->id)->where('book_id', $guestItem->book_id)->exists();
                $alreadyOwned = $user->userBooks()->where('book_id', $guestItem->book_id)->active()->exists();
                if ($existsInUserCart || $alreadyOwned) {
                    $guestItem->delete();
                    $skipped++;
                    continue;
                }
                $guestItem->update(['user_id' => $user->id]);
                $merged++;
            }

            return [
                'merged'  => $merged,
                'skipped' => $skipped,
            ];
        });
    }
}
