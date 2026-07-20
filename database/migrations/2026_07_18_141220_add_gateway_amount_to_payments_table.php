<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('gateway_amount', 12, 2)->nullable()->after('amount');
            $table->string('gateway_currency', 3)->default('EGP')->after('currency');
            $table->decimal('exchange_rate_used', 12, 6)->nullable()->after('gateway_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway_amount', 'gateway_currency', 'exchange_rate_used']);
        });
    }
};
