<?php

namespace App\Http\Controllers\Application\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ResponseApi ;

    public function trackingMyOrder(Request $request)
    {
        $actor = auth()->user('user-api') ;
        $orders = Order::with('items')->where('user_id', $actor->id)->get();
        return $this->successApi($orders, 'Orders retrieved successfully');
    }
}
