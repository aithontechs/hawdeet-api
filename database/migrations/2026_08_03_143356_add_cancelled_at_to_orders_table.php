<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            $table->foreignId('cancelled_by_admin_id')->nullable()
                            ->constrained('admins')->nullOnDelete()->after('cancellation_reason');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_by_admin_id');
            $table->string('refund_reason')->nullable()->after('refunded_at');
        });

    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
            $table->dropColumn('cancellation_reason');
            $table->dropColumn('cancelled_by_admin_id');
            $table->dropColumn('refunded_at');
            $table->dropColumn('refund_reason');
        });

    }
};
