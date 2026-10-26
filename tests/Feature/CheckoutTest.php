<?php

use App\Models\CafeTable;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RazorpayGateway;
use Livewire\Livewire;
use Razorpay\Api\Errors\BadRequestError;
use Razorpay\Api\Errors\SignatureVerificationError;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->table = CafeTable::create(['name' => 'T1', 'capacity' => 4, 'status' => 'occupied']);
    $this->order = Order::create([
        'order_number' => '1000000001',
        'cafe_table_id' => $this->table->id,
        'status' => 'open',
        'subtotal' => 0,
        'total_amount' => 0,
    ]);

    session()->put('order', [
        'order_id' => $this->order->id,
        'table_id' => $this->table->id,
        'table_name' => 'T1',
        'customer_name' => 'Ana',
        'guests' => 2,
        'order_type' => 'dine',
    ]);
});

function billItem(Order $order, array $attributes = []): OrderItem
{
    $menu = MenuItem::factory()->create([
        'category_id' => test()->category->id,
        'price' => $attributes['unit_price'] ?? 50,
    ]);

    return $order->orderItems()->create([
        'menu_item_id' => $menu->id,
        'item_name' => 'Coffee',
        'unit_price' => 50,
        'quantity' => 1,
        'subtotal' => 50,
        'status' => 'pending',
        ...$attributes,
    ]);
}

test('checkout redirects to select table when the session has no open order', function () {
    session()->forget('order');

    Livewire::test('pages::checkout')
        ->assertRedirect(route('select-table'));
});

test('checkout redirects to select table when the order is no longer open', function () {
    $this->order->update(['status' => 'cancelled']);

    Livewire::test('pages::checkout')
        ->assertRedirect(route('select-table'));

    expect(session('order'))->toBeNull();
});

test('the bill lists payable items and leaves out cancelled ones', function () {
    billItem($this->order, [
        'item_name' => 'Coffee',
        'note' => 'no sugar',
        'status' => 'preparing',
    ]);
    billItem($this->order, [
        'item_name' => 'Cake',
        'status' => 'cancelled',
        'subtotal' => 80,
        'unit_price' => 80,
    ]);
    $this->order->update([
        'tax_amount' => 5,
        'discount_amount' => 10,
        'total_amount' => 999,
    ]);

    Livewire::test('pages::checkout')
        ->assertSee('Coffee')
        ->assertSee('no sugar')
        ->assertSee('Preparing')
        ->assertSee('5.00')
        ->assertSee('10.00')
        ->assertSee('45.00')
        ->assertDontSee('Cake')
        ->assertDontSee('999');
});

test('paying by upi marks the order paid and frees the table', function () {
    billItem($this->order);
    $this->freezeTime();

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'upi')
        ->call('pay')
        ->assertRedirect(route('select-table'));

    $paid = $this->order->fresh();

    expect($paid)
        ->status->toBe('paid')
        ->payment_method->toBe('upi')
        ->subtotal->toEqual(50)
        ->total_amount->toEqual(50)
        ->and($paid->paid_at->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'))
        ->and($paid->billed_at->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'))
        ->and($this->table->fresh()->status)->toBe('available')
        ->and(session('order'))->toBeNull()
        ->and(session('status'))->toBe('Payment received for table T1. The table is free.');
});

test('paying by cash shows the change and records the payment when the amount covers the bill', function () {
    billItem($this->order);

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'cash')
        ->set('amountReceived', '80')
        ->assertSee('30.00')
        ->call('pay')
        ->assertRedirect(route('select-table'));

    expect($this->order->fresh())
        ->status->toBe('paid')
        ->payment_method->toBe('cash')
        ->total_amount->toEqual(50);
});

test('a fully discounted bill can be paid without cash tendered', function () {
    billItem($this->order);
    $this->order->update(['discount_amount' => 50]);

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'cash')
        ->call('pay')
        ->assertRedirect(route('select-table'));

    expect($this->order->fresh())
        ->status->toBe('paid')
        ->total_amount->toEqual(0);
});

test('cash that does not cover the bill is rejected', function () {
    billItem($this->order);

    Livewire::test('pages::checkout')
        ->set('amountReceived', '10')
        ->call('pay')
        ->assertNoRedirect()
        ->assertSee('Amount received must cover the bill of ₹50.00.');

    expect($this->order->fresh()->status)->toBe('open')
        ->and($this->table->fresh()->status)->toBe('occupied');
});

test('an empty cash amount is rejected', function () {
    billItem($this->order);

    Livewire::test('pages::checkout')
        ->set('amountReceived', '')
        ->call('pay')
        ->assertNoRedirect()
        ->assertSee('Enter the amount received.');

    expect($this->order->fresh()->status)->toBe('open');
});

test('a cash amount that is not a number is rejected', function () {
    billItem($this->order);

    Livewire::test('pages::checkout')
        ->set('amountReceived', 'abc')
        ->call('pay')
        ->assertNoRedirect()
        ->assertSee('Amount received must be a number.');

    expect($this->order->fresh()->status)->toBe('open');
});

test('an unknown payment method is rejected', function () {
    billItem($this->order);

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'voucher')
        ->call('pay')
        ->assertNoRedirect()
        ->assertSee('Choose cash, RazorPay, or UPI.');

    expect($this->order->fresh()->status)->toBe('open');
});

test('payment is refused when every item was cancelled', function () {
    billItem($this->order, ['status' => 'cancelled', 'item_name' => 'Cake']);

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'upi')
        ->call('pay')
        ->assertNoRedirect()
        ->assertSee('Nothing to bill yet');

    expect($this->order->fresh()->status)->toBe('open')
        ->and($this->table->fresh()->status)->toBe('occupied');
});

test('paying does not close a different table\'s order', function () {
    $otherTable = CafeTable::create(['name' => 'T2', 'capacity' => 2, 'status' => 'occupied']);
    $otherOrder = Order::create([
        'order_number' => '1000000002',
        'cafe_table_id' => $otherTable->id,
        'status' => 'open',
        'subtotal' => 50,
        'total_amount' => 50,
    ]);
    billItem($this->order);
    billItem($otherOrder, ['item_name' => 'Tea']);

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'upi')
        ->call('pay');

    expect($otherOrder->fresh()->status)->toBe('open')
        ->and($otherTable->fresh()->status)->toBe('occupied')
        ->and($this->order->fresh()->status)->toBe('paid');
});

test('paying with razorpay opens checkout and leaves the table occupied', function () {
    billItem($this->order);
    config([
        'services.razorpay.key' => 'rzp_test_key',
        'services.razorpay.secret' => 'rzp_test_secret',
    ]);
    mock(RazorpayGateway::class)
        ->shouldReceive('createOrder')
        ->once()
        ->with(5000, '1000000001')
        ->andReturn('order_test_123');

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'razorpay')
        ->assertSee('Pay with Razorpay')
        ->assertSee('A Razorpay window will open. The table stays occupied until that payment succeeds.')
        ->call('pay')
        ->assertNoRedirect()
        ->assertSet('awaitingRazorpay', true)
        ->assertDispatched('open-razorpay', function (string $name, array $params): bool {
            expect($params['key'])->toBe('rzp_test_key')
                ->and($params['amount'])->toBe(5000)
                ->and($params['currency'])->toBe('INR')
                ->and($params['orderId'])->toBe('order_test_123')
                ->and($params['prefillName'])->toBe('Ana')
                ->and(json_encode($params))->not->toContain('rzp_test_secret');

            return true;
        })
        ->call('razorpayDismissed')
        ->assertSet('awaitingRazorpay', false);

    expect($this->order->fresh())
        ->status->toBe('open')
        ->razorpay_order_id->toBe('order_test_123')
        ->razorpay_payment_id->toBeNull()
        ->paid_at->toBeNull()
        ->and($this->table->fresh()->status)->toBe('occupied')
        ->and(session('order'))->not->toBeNull();
});

test('a confirmed razorpay payment marks the order paid and frees the table', function () {
    billItem($this->order);
    $this->freezeTime();
    config([
        'services.razorpay.key' => 'rzp_test_key',
        'services.razorpay.secret' => 'rzp_test_secret',
    ]);
    $gateway = mock(RazorpayGateway::class);
    $gateway->shouldReceive('createOrder')->once()->with(5000, '1000000001')->andReturn('order_test_123');
    $gateway->shouldReceive('verifyPayment')->once()->with('order_test_123', 'pay_test_123', 'signed');

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'razorpay')
        ->call('pay')
        ->set('paymentMethod', 'cash')
        ->call('confirmRazorpay', 'pay_test_123', 'order_test_123', 'signed')
        ->assertRedirect(route('select-table'));

    $paid = $this->order->fresh();

    expect($paid)
        ->status->toBe('paid')
        ->payment_method->toBe('razorpay')
        ->razorpay_order_id->toBe('order_test_123')
        ->razorpay_payment_id->toBe('pay_test_123')
        ->total_amount->toEqual(50)
        ->and($paid->paid_at->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'))
        ->and($this->table->fresh()->status)->toBe('available')
        ->and(session('order'))->toBeNull();
});

test('a razorpay signature that does not match leaves the table occupied', function () {
    billItem($this->order);
    config([
        'services.razorpay.key' => 'rzp_test_key',
        'services.razorpay.secret' => 'rzp_test_secret',
    ]);
    $gateway = mock(RazorpayGateway::class);
    $gateway->shouldReceive('createOrder')->once()->andReturn('order_test_123');
    $gateway->shouldReceive('verifyPayment')->once()->andThrow(new SignatureVerificationError('Invalid signature passed'));

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'razorpay')
        ->call('pay')
        ->call('confirmRazorpay', 'pay_test_123', 'order_test_123', 'forged')
        ->assertNoRedirect()
        ->assertSee('Razorpay could not confirm this payment. The table is still open.');

    expect($this->order->fresh()->status)->toBe('open')
        ->and($this->order->fresh()->razorpay_payment_id)->toBeNull()
        ->and($this->table->fresh()->status)->toBe('occupied');
});

test('a razorpay payment for a different order does not close this bill', function () {
    billItem($this->order);
    config([
        'services.razorpay.key' => 'rzp_test_key',
        'services.razorpay.secret' => 'rzp_test_secret',
    ]);
    $gateway = mock(RazorpayGateway::class);
    $gateway->shouldReceive('createOrder')->once()->andReturn('order_test_123');
    $gateway->shouldReceive('verifyPayment')->never();

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'razorpay')
        ->call('pay')
        ->call('confirmRazorpay', 'pay_test_123', 'order_other', 'signed')
        ->assertNoRedirect()
        ->assertSee('This payment does not match the open bill.');

    expect($this->order->fresh()->status)->toBe('open')
        ->and($this->table->fresh()->status)->toBe('occupied');
});

test('razorpay is refused when the keys are missing', function () {
    billItem($this->order);
    config([
        'services.razorpay.key' => null,
        'services.razorpay.secret' => null,
    ]);
    mock(RazorpayGateway::class)->shouldReceive('createOrder')->never();

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'razorpay')
        ->call('pay')
        ->assertNoRedirect()
        ->assertNotDispatched('open-razorpay')
        ->assertSee('Razorpay is not set up yet. Add RAZOR_KEY and RAZOR_SECRET to the .env file.');

    expect($this->order->fresh()->status)->toBe('open');
});

test('razorpay shows an error when the api keys are rejected', function () {
    billItem($this->order);
    config([
        'services.razorpay.key' => 'rzp_test_key',
        'services.razorpay.secret' => 'rzp_test_secret',
    ]);
    mock(RazorpayGateway::class)
        ->shouldReceive('createOrder')
        ->once()
        ->andThrow(new BadRequestError('Authentication failed', 'BAD_REQUEST_ERROR', 401));

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'razorpay')
        ->call('pay')
        ->assertNoRedirect()
        ->assertNotDispatched('open-razorpay')
        ->assertSee('Razorpay refused these API keys. Check RAZOR_KEY and RAZOR_SECRET in the .env file.');

    expect($this->order->fresh()->status)->toBe('open')
        ->and($this->table->fresh()->status)->toBe('occupied');
});

test('razorpay is refused when the bill is under one rupee', function () {
    billItem($this->order);
    $this->order->update(['discount_amount' => 49.50]);
    config([
        'services.razorpay.key' => 'rzp_test_key',
        'services.razorpay.secret' => 'rzp_test_secret',
    ]);
    mock(RazorpayGateway::class)->shouldReceive('createOrder')->never();

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'razorpay')
        ->call('pay')
        ->assertNoRedirect()
        ->assertNotDispatched('open-razorpay')
        ->assertSee('Razorpay cannot collect less than ₹1.00. Take this bill in cash.');

    expect($this->order->fresh()->status)->toBe('open')
        ->and($this->table->fresh()->status)->toBe('occupied');
});

test('a fully discounted razorpay bill is paid without opening checkout', function () {
    billItem($this->order);
    $this->order->update(['discount_amount' => 50]);
    mock(RazorpayGateway::class)->shouldReceive('createOrder')->never();

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'razorpay')
        ->call('pay')
        ->assertNotDispatched('open-razorpay')
        ->assertRedirect(route('select-table'));

    expect($this->order->fresh())
        ->status->toBe('paid')
        ->payment_method->toBe('razorpay')
        ->total_amount->toEqual(0)
        ->and($this->table->fresh()->status)->toBe('available');
});
