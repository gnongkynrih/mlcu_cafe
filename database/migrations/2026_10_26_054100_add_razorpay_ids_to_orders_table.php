<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remember which Razorpay payment belongs to this bill.
     *
     * Without these ids, a payment created for a different (cheaper) order
     * could be submitted as proof that this table was paid.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('razorpay_order_id')->nullable()->after('payment_method');
            $table->string('razorpay_payment_id')->nullable()->after('razorpay_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['razorpay_order_id', 'razorpay_payment_id']);
        });
    }
};
