<?php

use App\Exports\OrderExport;
use App\Models\CafeTable;
use App\Models\Order;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

test('the summary excel export has one row per day and a total row', function () {
    Excel::fake();
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
        'subtotal' => 30,
        'total_amount' => 30,
        'paid_at' => '2026-10-30 18:00:00',
        'payment_method' => 'upi',
    ]);

    Livewire::test('pages::report.sale-report')
        ->set('dateFrom', '2026-10-29')
        ->set('dateTo', '2026-10-30')
        ->call('generateReport')
        ->call('downloadExcel');

    Excel::assertDownloaded('sale-report-summary-2026-10-29-to-2026-10-30.xlsx', function (OrderExport $export) {
        $rows = $export->collection();

        return $export->headings() === ['Date', 'Total (₹)']
            && $rows->count() === 3
            && $rows->last() === ['Total', 110.0];
    });
});

test('the detail excel export lists each payment with its razorpay reference', function () {
    Excel::fake();
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
    Order::create([
        'order_number' => '1000000002',
        'cafe_table_id' => $table->id,
        'status' => 'paid',
        'subtotal' => 20,
        'total_amount' => 20,
        'paid_at' => '2026-10-29 18:00:00',
        'payment_method' => 'cash',
    ]);

    Livewire::test('pages::report.sale-report')
        ->set('dateFrom', '2026-10-29')
        ->set('dateTo', '2026-10-29')
        ->call('generateDetailsReport')
        ->call('downloadExcel');

    Excel::assertDownloaded('sale-report-details-2026-10-29-to-2026-10-29.xlsx', function (OrderExport $export) {
        $rows = $export->collection();

        return $export->headings() === ['Paid At', 'Razorpay Reference', 'Amount (₹)']
            && $rows[0][1] === 'pay_test_123'
            && $rows[1][1] === '—'
            && $rows->last() === ['Total', '', 100.0];
    });
});

test('the excel export colours the header, highlights the total and freezes the header row', function () {
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

    $file = tempnam(sys_get_temp_dir(), 'sale-report');
    file_put_contents($file, Excel::raw(new OrderExport(Order::all(), 'details'), Maatwebsite\Excel\Excel::XLSX));
    $sheet = IOFactory::load($file)->getActiveSheet();
    unlink($file);

    expect($sheet->getTitle())->toBe('Detail')
        ->and($sheet->getFreezePane())->toBe('A2')
        ->and($sheet->getAutoFilter()->getRange())->toBe('A1:C2')
        ->and($sheet->getStyle('A1')->getFill()->getStartColor()->getARGB())->toBe('FFF59E0B')
        ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getCell('A3')->getValue())->toBe('Total')
        ->and($sheet->getStyle('C3')->getFill()->getStartColor()->getARGB())->toBe('FFFEF3C7')
        ->and($sheet->getStyle('C3')->getBorders()->getTop()->getBorderStyle())->toBe('double');
});
