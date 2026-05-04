<?php

namespace App\Services\Purchase ;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookPurchaseService
{
    public function __construct(){}

    public function purchase(User $user, array $bookIds ,  $finalTotal , $discount , $totalBefore): Order
    {
        return DB::transaction(function () use ($user, $bookIds ,  $finalTotal , $discount , $totalBefore) {

            $books = $this->validateBooks($user, $bookIds);

            $order = Order::create([
                'user_id'        => $user->id,
                'order_number'   => Order::generateOrderNumber(),
                'subtotal'       => $totalBefore,
                'discount'       => $discount,
                'total'          => $finalTotal,
            ]);

            foreach ($books as $book) {
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'book_id'  => $book->id,
                    'book_name' => $book->title,
                    'price' => $book->price,
                ]);
            }

            return $order->load('items.book');
        });
    }


    public function validateBooks(User $user, array $bookIds)
    {
        $books = Book::whereIn('id', $bookIds)->where('published', true)->get();

        if ($books->count() !== count($bookIds)) {
            throw ValidationException::withMessages([
                'books' => 'In this order , currently Some books is invalid',
            ]);
        }

        $alreadyOwned = $user->userBooks()->whereIn('book_id', $bookIds)->where('access_type', 'purchase')->active()->pluck('book_id');

        if ($alreadyOwned->isNotEmpty()) {
            $titles = $books->whereIn('id', $alreadyOwned)->pluck('title')->join('، ');
            throw ValidationException::withMessages([
                'books' => "this books have access on {$titles}",
            ]);
        }

        return $books;
    }





}
