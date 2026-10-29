<?php

use App\Models\CafeTable;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(Permission::findOrCreate('take order'));

    $table = CafeTable::create(['name' => 'T1', 'capacity' => 4, 'status' => 'available']);
    $this->order = Order::create([
        'order_number' => '1000000001',
        'cafe_table_id' => $table->id,
        'status' => 'paid',
        'payment_method' => 'upi',
        'subtotal' => 50,
        'total_amount' => 50,
        'paid_at' => now(),
    ]);

    $menu = MenuItem::factory()->create(['category_id' => Category::factory()->create()->id]);
    $this->order->orderItems()->create([
        'menu_item_id' => $menu->id,
        'item_name' => 'Coffee',
        'unit_price' => 50,
        'quantity' => 1,
        'subtotal' => 50,
        'status' => 'served',
    ]);
});

test('a paid order downloads a pdf receipt', function () {
    $response = $this->actingAs($this->user)->get(route('orders.receipt', $this->order));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('receipt-1000000001.pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

test('an open order has no receipt', function () {
    $this->order->update(['status' => 'open']);

    $this->actingAs($this->user)
        ->get(route('orders.receipt', $this->order))
        ->assertNotFound();
});

test('guests cannot download a receipt', function () {
    $this->get(route('orders.receipt', $this->order))
        ->assertRedirect(route('login'));
});
