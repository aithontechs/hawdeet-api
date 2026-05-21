<?php

namespace App\Services\Purchase ;

use App\Models\Order;
use App\Models\UserBook;

class BookAccessGrantService
{
    public function grantBookAccess(Order $order)
    {
        foreach ($order->items as $item) {
            
            if ($item->item_type !== 'digital') {
                continue;
            }
            UserBook::updateOrCreate(
                [
                    'user_id'     => $order->user_id,
                    'book_id'     => $item->book_id,
                    'access_type' => 'purchase',
                ],
                [
                    'order_item_id' => $item->id,
                    'granted_at'    => now(),
                    'expires_at'    => now()->addDays($item->access_duration_days),
                ]
            );
        }
    }

}
