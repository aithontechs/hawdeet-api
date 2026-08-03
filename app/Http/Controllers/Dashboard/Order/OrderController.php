<?php

namespace App\Http\Controllers\Dashboard\Order;

use App\Exports\OrdersExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\OrderCancellationService;
use App\Traits\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class OrderController extends Controller
{
    use ResponseApi ;

    public function __construct(
        private OrderCancellationService $cancellationService,
    ) {}

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

        return $this->successApi($order,'Order details fetched successfully');
    }

    public function updateShippingOrderStatus(Request $request , Order $order)
    {
        $this->authorize('status', Order::class) ;
        $request->validate([
            'status' => 'required|in:processing,shipped,delivered,cancelled,returned',
            'reason' => 'nullable|string|max:255',
        ]);

        abort_unless($order->has_physical , 400 , 'This order does not require shipping.') ;

        $allowedTransitions = [
            null => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => ['returned'],
            'cancelled' => [],
            'returned' => [],
        ];

        $currentStatus = $order->shipping_status;
        $newStatus = $request->status;

        abort_unless(in_array($newStatus, $allowedTransitions[$currentStatus] ?? []),422,
            "Cannot change shipping status from {$currentStatus} to {$newStatus}."
        );

        if ($newStatus === 'cancelled') {
            $order = $this->cancellationService->cancelByAdmin(
                $order, auth('admin-api')->user(), $request->reason
            );
            return $this->successApi($order, 'Order cancelled successfully.');
        }

        if ($newStatus === 'returned') {
            $order = $this->cancellationService->markReturned(
                $order, auth('admin-api')->user(), $request->reason
            );
            return $this->successApi($order, 'Order marked as returned and refunded.');
        }

        $order = DB::transaction(function () use ($order, $newStatus) {
            $data = ['shipping_status' => $newStatus];

            if ($newStatus === 'shipped' && $order->shipped_at === null) {
                $data['shipped_at'] = now();
            }

            if ($newStatus === 'delivered' && $order->delivered_at === null) {
                $data['delivered_at'] = now();

                if ($order->payment_method === 'cash' && $order->payment_status === 'pending') {
                    $data['payment_status'] = 'paid';
                    $data['paid_at'] = now();

                    $payment = $order->payments()
                                    ->where('payment_gateway', 'cash_on_delivery')
                                    ->where('status', 'pending')
                                    ->latest()
                                    ->first();

                    if ($payment) {
                        $payment->update([
                            'status'  => 'paid',
                            'paid_at' => now(),
                        ]);
                    } else {
                        Log::warning("COD order #{$order->order_number} marked paid but no pending cash_on_delivery Payment found.", [
                            'order_id' => $order->id,
                        ]);
                    }
                }
            }

            $order->update($data);

            return $order->fresh();
        });
        return $this->successApi($order , 'Shipping status updated successfully.');
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
