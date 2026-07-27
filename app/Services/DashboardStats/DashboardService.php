<?php

namespace App\Services\DashboardStats ;

use App\Models\Order;

class DashboardService
{
    public function getStats()
    {
        return $this->getLatestOrdersCreated() ;
    }

    public function getLatestOrdersCreated()
    {
        $orders = Order::select(['id' , 'order_number' , 'created_at' , 'user_id'])
                    ->with('user:id,name')->latest()->limit(5)->get()->map(function($order){
                        return [
                            'id' => $order->id,
                            'order_number' => $order->order_number ,
                            'user' => $order->user?->name ,
                            'created_at' => $order->created_at->format('Y-M-D h:i:s')
                        ];
                    });

        return $orders ;
    }

    public function TopSellingBooks()
    {
        
    }
}
