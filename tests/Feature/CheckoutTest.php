<?php

use App\Models\CafeTable;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Livewire;

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

test('paying by card or upi marks the order paid and frees the table', function (string $method) {
    billItem($this->order);
    $this->freezeTime();

    Livewire::test('pages::checkout')
        ->set('paymentMethod', $method)
        ->call('pay')
        ->assertRedirect(route('select-table'));

    $paid = $this->order->fresh();

    expect($paid)
        ->status->toBe('paid')
        ->payment_method->toBe($method)
        ->subtotal->toEqual(50)
        ->total_amount->toEqual(50)
        ->and($paid->paid_at->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'))
        ->and($paid->billed_at->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'))
        ->and($this->table->fresh()->status)->toBe('available')
        ->and(session('order'))->toBeNull()
        ->and(session('status'))->toBe('Payment received for table T1. The table is free.');
})->with(['card', 'upi']);

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
        ->assertSee('Choose cash, card, or UPI.');

    expect($this->order->fresh()->status)->toBe('open');
});

test('payment is refused when every item was cancelled', function () {
    billItem($this->order, ['status' => 'cancelled', 'item_name' => 'Cake']);

    Livewire::test('pages::checkout')
        ->set('paymentMethod', 'card')
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
        ->set('paymentMethod', 'card')
        ->call('pay');

    expect($otherOrder->fresh()->status)->toBe('open')
        ->and($otherTable->fresh()->status)->toBe('occupied')
        ->and($this->order->fresh()->status)->toBe('paid');
});
