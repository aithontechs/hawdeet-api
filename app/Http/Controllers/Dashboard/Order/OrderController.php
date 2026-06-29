<?php

namespace App\Http\Controllers\Dashboard\Order;

use App\Exports\OrdersExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class OrderController extends Controller
{
    use ResponseApi ;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class) ;
        $orders = Order::with('user:id,name')->filter($request)->latest()->paginate(15) ;
        return $this->successApi($orders , 'Orders fetched successully') ;
    }

    public function show($id)
    {
        $this->authorize('show', Order::class) ;
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
        $this->authorize('viewAny', Order::class) ;
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

    public function export(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $fileName = 'orders_' . now()->format('Y_m_d_His') . '.xlsx';

        return Excel::download(new OrdersExport($request), $fileName);
    }


}
