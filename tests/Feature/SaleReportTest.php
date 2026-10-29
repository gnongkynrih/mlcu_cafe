<?php

use App\Models\CafeTable;
use App\Models\Order;
use Livewire\Livewire;

test('the sale report asks for a date range before anything is generated', function () {
    Livewire::test('pages::report.sale-report')
        ->assertSee('Sale Report')
        ->assertSee('Summary')
        ->assertSee('Detail')
        ->assertSee('No report yet')
        ->assertDontSee('No paid bills');
});

test('the summary report shows one total for each day', function () {
    $table = CafeTable::create(['name' => 'T1', 'capacity' => 4, 'status' => 'available']);

    Order::create([
        'order_number' => '1000000001',
        'cafe_table_id' => $table->id,
        'status' => 'paid',
        'subtotal' => 80,
        'total_amount' => 80,
        'paid_at' => '2026-10-29 09:15:00',
        'payment_method' => 'cash',
    ]);
    Order::create([
        'order_number' => '1000000002',
        'cafe_table_id' => $table->id,
        'status' => 'paid',
        'subtotal' => 20,
        'total_amount' => 20,
        'paid_at' => '2026-10-29 18:00:00',
        'payment_method' => 'upi',
    ]);

    Livewire::test('pages::report.sale-report')
        ->set('dateFrom', '2026-10-29')
        ->set('dateTo', '2026-10-29')
        ->call('generateReport')
        ->assertSee('Summary')
        ->assertSee('29 Oct 2026')
        ->assertSee('100.00')
        ->assertSee('1 day')
        ->assertDontSee('pay_test_123');
});

test('the detail report lists each payment', function () {
    $table = CafeTable::create(['name' => 'T1', 'capacity' => 4, 'status' => 'available']);

    Order::create([
        'order_number' => '1000000001',
        'cafe_table_id' => $table->id,
        'status' => 'paid',
        'subtotal' => 80,
        'total_amount' => 80,
        'paid_at' => '2026-10-29 09:15:00',
        'payment_method' => 'razorpay',
        'razorpay_payment_id' => 'pay_test_123',
    ]);

    Livewire::test('pages::report.sale-report')
        ->set('dateFrom', '2026-10-29')
        ->set('dateTo', '2026-10-29')
        ->call('generateDetailsReport')
        ->assertSee('Detail')
        ->assertSee('29 Oct 2026, 09:15 AM')
        ->assertSee('pay_test_123')
        ->assertSee('80.00')
        ->assertSee('1 payment');
});

test('a date range with no payments shows an empty report', function () {
    Livewire::test('pages::report.sale-report')
        ->set('dateFrom', '2026-01-01')
        ->set('dateTo', '2026-01-02')
        ->call('generateReport')
        ->assertSee('No paid bills')
        ->assertDontSee('₹');
});
