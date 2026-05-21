<?php

namespace App\Http\Controllers\Dashboard\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ResponseApi ;

    public function index(Request $request)
    {
        $orders = Order::with('user:id,name')->filter($request)->latest()->paginate(15) ;
        return $this->successApi($orders , 'Orders fetched successully') ;
    }

    public function show($id)
    {
        $order = Order::with([
                'items',
                'payment:id,order_id,payment_gateway,paymob_order_id,gateway_transaction_id,paid_at,created_at',
            ])
            ->findOrFail($id);

        if ($order->has_physical) {
            $order->load([
                'physicalOrder.shippingAddress'
            ]);
        }

        return $this->successApi(
            $order,
            'Order details fetched successfully'
        );
    }


    public function stats()
    {
        $stats = Order::selectRaw("
                COUNT(*) as total_orders,

                SUM(CASE
                        WHEN payment_status = 'paid'
                        THEN 1
                        ELSE 0
                    END
                ) as paid_orders,

                SUM(CASE
                        WHEN payment_status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ) as pending_orders,

                SUM(CASE
                        WHEN payment_status = 'paid'
                        THEN total
                        ELSE 0
                    END
                ) as total_revenue,

                AVG(total) as average_order_value,

                SUM(CASE
                        WHEN DATE(created_at) = CURDATE()
                        THEN 1
                        ELSE 0
                    END
                ) as today_orders
            ")
            ->first();

        return $this->successApi(
            $stats,
            'Orders statistics fetched successfully'
        );
    }

}
