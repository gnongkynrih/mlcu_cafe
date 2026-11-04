<?php

use App\Models\CafeTable;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(Permission::findOrCreate('take order'));
    $this->table = CafeTable::create(['name' => 'T1', 'capacity' => 4, 'status' => 'available']);
});

function paidOrder(array $attributes = []): Order
{
    static $number = 1000000000;

    return Order::create([
        'order_number' => (string) ++$number,
        'cafe_table_id' => test()->table->id,
        'status' => 'paid',
        'payment_method' => 'cash',
        'subtotal' => 0,
        'total_amount' => 0,
        'paid_at' => now(),
        ...$attributes,
    ]);
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the cards show today\'s sales, bills, average bill and free tables', function () {
    $this->travelTo('2026-11-03 14:00:00');
    CafeTable::create(['name' => 'T2', 'capacity' => 2, 'status' => 'occupied']);
    paidOrder(['total_amount' => 100, 'paid_at' => '2026-11-03 10:00:00']);
    paidOrder(['total_amount' => 50, 'paid_at' => '2026-11-03 12:00:00', 'payment_method' => 'upi']);
    paidOrder(['total_amount' => 100, 'paid_at' => '2026-11-02 12:00:00']);
    paidOrder(['status' => 'open', 'paid_at' => null]);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSet('stats', [
            'todaySales' => 150.0,
            'salesChange' => 50.0,
            'billsToday' => 2,
            'averageBill' => 75.0,
            'freeTables' => 1,
            'totalTables' => 2,
            'openBills' => 1,
        ])
        ->assertSee('₹150.00')
        ->assertSee('+50% on yesterday')
        ->assertSee('1 / 2');
});

test('the 7 day chart has a bar for every day, including days with no sales', function () {
    $this->travelTo('2026-11-03 14:00:00');
    paidOrder(['total_amount' => 40, 'paid_at' => '2026-11-03 09:00:00']);
    paidOrder(['total_amount' => 60, 'paid_at' => '2026-10-28 09:00:00']);
    paidOrder(['total_amount' => 999, 'paid_at' => '2026-10-27 23:59:00']);

    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSet('dailySales', [
            'labels' => ['Wed 28', 'Thu 29', 'Fri 30', 'Sat 31', 'Sun 1', 'Mon 2', 'Today'],
            'totals' => [60.0, 0, 0, 0, 0, 0, 40.0],
        ]);
});

test('payment methods and top items only count paid bills from the last 7 days', function () {
    $this->travelTo('2026-11-03 14:00:00');
    $menuItem = MenuItem::factory()->create(['category_id' => Category::factory()->create()->id]);
    $addItem = fn (Order $order, string $name, int $quantity, string $status = 'served') => $order->orderItems()->create([
        'menu_item_id' => $menuItem->id,
        'item_name' => $name,
        'unit_price' => 10,
        'quantity' => $quantity,
        'subtotal' => 10 * $quantity,
        'status' => $status,
    ]);

    $cash = paidOrder(['total_amount' => 30]);
    $upi = paidOrder(['total_amount' => 70, 'payment_method' => 'upi']);
    $old = paidOrder(['total_amount' => 500, 'paid_at' => '2026-10-01 10:00:00']);
    $open = paidOrder(['status' => 'open', 'paid_at' => null]);

    $addItem($cash, 'Latte', 2);
    $addItem($upi, 'Latte', 1);
    $addItem($upi, 'Muffin', 2);
    $addItem($upi, 'Cake', 9, 'cancelled');
    $addItem($old, 'Tea', 50);
    $addItem($open, 'Tea', 50);

    $dashboard = Livewire::actingAs($this->user)->test('pages::dashboard');

    expect($dashboard->get('paymentMethods'))->toBe([
        'labels' => ['UPI', 'Cash'],
        'totals' => [70.0, 30.0],
        'colours' => ['#14b8a6', '#f59e0b'],
    ])
        ->and($dashboard->get('charts')['topItems']['labels'])->toBe(['Latte', 'Muffin'])
        ->and($dashboard->get('charts')['topItems']['datasets'][0]['data'])->toBe([3, 2]);
});

test('the dashboard shows empty states when nothing has been sold', function () {
    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee('No payments yet.')
        ->assertSee('Nothing sold yet.')
        ->assertSee('No bills paid yet.')
        ->assertSee('No sales yesterday to compare');
});

test('management quick links are only shown to admins', function () {
    Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->assertSee(route('select-table'))
        ->assertDontSee(route('menu-management'));
});
