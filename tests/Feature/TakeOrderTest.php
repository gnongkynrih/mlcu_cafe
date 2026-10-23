<?php

use App\Models\CafeTable;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

function menuItem(array $attributes = []): MenuItem
{
    return MenuItem::factory()->create(['category_id' => test()->category->id, ...$attributes]);
}

test('tapping a menu item adds it to the cart and tapping again increases the quantity', function () {
    $coffee = menuItem(['name' => 'Coffee', 'price' => 50]);

    Livewire::test('pages::take-order')
        ->call('addItem', $coffee->id)
        ->assertSet("cart.{$coffee->id}.qty", 1)
        ->assertSet("cart.{$coffee->id}.price", 50.0)
        ->call('addItem', $coffee->id)
        ->assertSet("cart.{$coffee->id}.qty", 2);
});

test('unavailable menu items cannot be added', function () {
    $soldOut = menuItem(['is_available' => false]);

    $page = Livewire::test('pages::take-order');

    expect(fn () => $page->call('addItem', $soldOut->id))->toThrow(ModelNotFoundException::class);
    $page->assertSet('cart', []);
});

test('decreasing the quantity to zero removes the item from the cart', function () {
    $coffee = menuItem();

    Livewire::test('pages::take-order')
        ->call('addItem', $coffee->id)
        ->call('increaseQty', $coffee->id)
        ->assertSet("cart.{$coffee->id}.qty", 2)
        ->call('decreaseQty', $coffee->id)
        ->assertSet("cart.{$coffee->id}.qty", 1)
        ->call('decreaseQty', $coffee->id)
        ->assertSet('cart', []);
});

test('an item can be removed from the cart', function () {
    $coffee = menuItem();

    Livewire::test('pages::take-order')
        ->call('addItem', $coffee->id)
        ->call('removeItem', $coffee->id)
        ->assertSet('cart', []);
});

test('sending to the kitchen saves the cart as order items and updates the order total', function () {
    $coffee = menuItem(['name' => 'Coffee', 'price' => 50]);
    $cake = menuItem(['name' => 'Cake', 'price' => 120]);

    Livewire::test('pages::take-order')
        ->call('addItem', $coffee->id)
        ->call('addItem', $coffee->id)
        ->call('addItem', $cake->id)
        ->set("cart.{$cake->id}.note", 'no nuts')
        ->call('sendToKitchen')
        ->assertSet('cart', [])
        ->assertSee('Sent to kitchen');

    expect(OrderItem::where('order_id', $this->order->id)->get())
        ->toHaveCount(2)
        ->sequence(
            fn ($item) => $item->item_name->toBe('Coffee')->quantity->toBe(2)->subtotal->toEqual(100)->status->toBe('pending'),
            fn ($item) => $item->item_name->toBe('Cake')->quantity->toBe(1)->note->toBe('no nuts'),
        );

    expect($this->order->fresh())
        ->subtotal->toEqual(220)
        ->total_amount->toEqual(220);
});

test('a second round is added to the items already sent', function () {
    $coffee = menuItem(['price' => 50]);

    $page = Livewire::test('pages::take-order')
        ->call('addItem', $coffee->id)
        ->call('sendToKitchen');

    $page->call('addItem', $coffee->id)
        ->call('sendToKitchen');

    expect(OrderItem::where('order_id', $this->order->id)->count())->toBe(2)
        ->and($this->order->fresh()->total_amount)->toEqual(100);
});

test('prices are taken from the database, not from the cart sent by the browser', function () {
    $coffee = menuItem(['price' => 50]);

    Livewire::test('pages::take-order')
        ->call('addItem', $coffee->id)
        ->set("cart.{$coffee->id}.price", 1)
        ->call('sendToKitchen');

    expect(OrderItem::firstWhere('order_id', $this->order->id))
        ->unit_price->toEqual(50)
        ->subtotal->toEqual(50);
});

test('sending an empty cart saves nothing', function () {
    Livewire::test('pages::take-order')
        ->call('sendToKitchen');

    expect(OrderItem::count())->toBe(0);
});

function sentItem(Order $order, array $attributes = []): OrderItem
{
    $menu = menuItem(['price' => 50]);

    return $order->orderItems()->create([
        'menu_item_id' => $menu->id,
        'item_name' => $menu->name,
        'unit_price' => 50,
        'quantity' => 1,
        'subtotal' => 50,
        'status' => 'pending',
        ...$attributes,
    ]);
}

test('a pending item can be cancelled with a reason and is removed from the total', function () {
    $keep = sentItem($this->order);
    $cancel = sentItem($this->order);
    $this->order->update(['subtotal' => 100, 'total_amount' => 100]);

    Livewire::test('pages::take-order')
        ->call('confirmCancelItem', $cancel->id)
        ->assertSet('showCancelItemModal', true)
        ->set('cancelReason', 'Customer changed mind')
        ->call('cancelItem')
        ->assertHasNoErrors()
        ->assertSet('showCancelItemModal', false);

    expect($cancel->fresh())
        ->status->toBe('cancelled')
        ->cancel_reason->toBe('Customer changed mind')
        ->cancelled_at->not->toBeNull()
        ->and($keep->fresh()->status)->toBe('pending')
        ->and($this->order->fresh()->total_amount)->toEqual(50);
});

test('cancelling an item requires a reason', function () {
    $item = sentItem($this->order);

    Livewire::test('pages::take-order')
        ->call('confirmCancelItem', $item->id)
        ->set('cancelReason', '')
        ->call('cancelItem')
        ->assertHasErrors(['cancelReason' => 'required']);

    expect($item->fresh()->status)->toBe('pending');
});

test('items the kitchen has started cannot be cancelled', function () {
    $item = sentItem($this->order, ['status' => 'preparing']);
    $page = Livewire::test('pages::take-order');

    expect(fn () => $page->call('confirmCancelItem', $item->id))->toThrow(ModelNotFoundException::class);
    expect($item->fresh()->status)->toBe('preparing');
});

test('items of another order cannot be cancelled', function () {
    $otherOrder = Order::create([
        'order_number' => '1000000002',
        'cafe_table_id' => $this->table->id,
        'status' => 'open',
        'subtotal' => 0,
        'total_amount' => 0,
    ]);
    $otherItem = sentItem($otherOrder);

    $page = Livewire::test('pages::take-order')
        ->set('cancellingItemId', $otherItem->id)
        ->set('cancelReason', 'Trying to cheat');

    expect(fn () => $page->call('cancelItem'))->toThrow(ModelNotFoundException::class);
    expect($otherItem->fresh()->status)->toBe('pending');
});

test('new items still add up correctly after an item was cancelled', function () {
    sentItem($this->order, ['status' => 'cancelled']);
    $coffee = menuItem(['price' => 30]);

    Livewire::test('pages::take-order')
        ->call('addItem', $coffee->id)
        ->call('sendToKitchen');

    expect($this->order->fresh()->total_amount)->toEqual(30);
});

test('the whole order can be cancelled while every item is pending', function () {
    $item = sentItem($this->order);

    Livewire::test('pages::take-order')
        ->call('confirmCancelOrder')
        ->assertSet('showCancelOrderModal', true)
        ->set('cancelReason', 'Guests left')
        ->call('cancelOrder')
        ->assertRedirect(route('select-table'));

    expect($item->fresh()->status)->toBe('cancelled')
        ->and($this->order->fresh())
        ->status->toBe('cancelled')
        ->total_amount->toEqual(0)
        ->and($this->table->fresh()->status)->toBe('available')
        ->and(session('order'))->toBeNull();
});

test('the order cannot be cancelled once the kitchen has started an item', function () {
    sentItem($this->order, ['status' => 'preparing']);

    Livewire::test('pages::take-order')
        ->assertDontSeeHtml('wire:click="confirmCancelOrder"')
        ->call('confirmCancelOrder')
        ->assertSet('showCancelOrderModal', false)
        ->set('cancelReason', 'Guests left')
        ->call('cancelOrder')
        ->assertNoRedirect();

    expect($this->order->fresh()->status)->toBe('open');
});

test('a cancelled order cannot be opened again from the session', function () {
    $this->order->update(['status' => 'cancelled']);

    Livewire::test('pages::take-order')
        ->assertRedirect(route('select-table'));

    expect(session('order'))->toBeNull();
});
