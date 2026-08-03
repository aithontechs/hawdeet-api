<?php

namespace App\Http\Controllers\Application\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\OrderCancellationService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ResponseApi ;

    public function __construct(
        private OrderCancellationService $cancellationService,
    ) {}

    public function trackingMyOrder(Request $request)
    {
        $actor = auth()->user('user-api') ;
        $orders = Order::with('items')->where('user_id', $actor->id)->latest()->get();
        return $this->successApi($orders, 'Orders retrieved successfully');
    }

    public function cancel(Request $request, Order $order)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);
        $order = $this->cancellationService->cancelByCustomer(
            $order, auth('user-api')->user(), $request->reason
        );
        return $this->successApi($order, 'تم إلغاء الطلب بنجاح.');
    }
}
