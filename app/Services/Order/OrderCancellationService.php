<?php

namespace App\Services\Order;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCancellationService
{
    private const CLOSED_SHIPPING_STATUSES = ['cancelled', 'returned'];

    public function cancelByCustomer(Order $order, User $user, ?string $reason = null): Order
    {
        abort_unless($order->user_id === $user->id, 403, 'Unauthorized.');

        abort_unless($order->has_physical && $order->payment_method === 'cash',422,
            'يمكن إلغاء الطلبات الورقية بنظام الدفع عند الاستلام فقط. لأي طلب آخر تواصل مع الدعم.'
        );

        abort_if(in_array($order->shipping_status, ['shipped', 'delivered', ...self::CLOSED_SHIPPING_STATUSES], true),
            422,
            'الأوردر لايمكن تغير حالته الان . تواصل مع الدعم لمساعدتك.'
        );
        return $this->executeCancel($order, $reason, adminId: null);
    }


    public function cancelByAdmin(Order $order, Admin $admin, ?string $reason = null): Order
    {
        abort_if(
            in_array($order->shipping_status, self::CLOSED_SHIPPING_STATUSES, true),
            422,
            'الأوردر غير متاح أصبح (ملغي أو مرتجع).'
        );

        return $this->executeCancel($order, $reason, adminId: $admin->id);
    }


    public function markReturned(Order $order, Admin $admin, ?string $reason = null): Order
    {
        abort_unless($order->shipping_status === 'delivered', 422, 'يقدر يترجع بس لو اتسلم فعلاً.');

        return DB::transaction(function () use ($order, $admin, $reason) {
            $this->restockPhysicalItems($order);

            $order->update([
                'shipping_status' => 'returned',
            ]);

            $this->processRefund($order, $reason, initiatedByAdminId: $admin->id);

            return $order->fresh();
        });
    }


    public function refundOnly(Order $order, Admin $admin, ?string $reason = null): Order
    {
        abort_unless($order->payment_status === 'paid', 422, 'الأوردر ده لسه مدفوعش، مفيش حاجة تترد.');

        return DB::transaction(function () use ($order, $admin, $reason) {
            $this->processRefund($order, $reason, initiatedByAdminId: $admin->id);

            // لو فيه محتوى رقمي منحه بالفعل، اسحبه
            // $this->grantService->revokeBookAccess($order);

            return $order->fresh();
        });
    }

    private function executeCancel(Order $order, ?string $reason, ?int $adminId): Order
    {
        return DB::transaction(function () use ($order, $reason, $adminId) {
            $this->restockPhysicalItems($order);

            $order->update([
                'shipping_status'       => $order->has_physical ? 'cancelled' : $order->shipping_status,
                'cancelled_at'          => now(),
                'cancellation_reason'   => $reason,
                'cancelled_by_admin_id' => $adminId,
            ]);

            if ($order->payment_status === 'paid') {
                $this->processRefund($order, $reason, initiatedByAdminId: $adminId);
            }

            return $order->fresh();
        });
    }

    private function restockPhysicalItems(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->item_type !== 'physical') {
                continue;
            }

            $column = $item->cover_type === 'hard_cover'
                ? 'physical_hard_cover_stock'
                : 'physical_stock';

            DB::table('books')->where('id', $item->book_id)->increment($column, $item->quantity);
        }
    }

    private function processRefund(Order $order, ?string $reason, ?int $initiatedByAdminId): void
    {
        if ($order->payment_method === 'cash') {
            $order->update([
                'payment_status' => 'refunded',
                'refunded_at'    => now(),
                'refund_reason'  => $reason,
            ]);

            Log::info("Cash order #{$order->order_number} marked refunded (manual cash return).", [
                'admin_id' => $initiatedByAdminId,
            ]);

            return;
        }

        $order->update([
            'payment_status' => 'refunded',
            'refunded_at'    => now(),
            'refund_reason'  => $reason,
        ]);

        Log::info("Order #{$order->order_number} refunded via admin.", [
            'admin_id' => $initiatedByAdminId,
        ]);
    }
}
