<?php

use App\Models\CafeTable;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Brand colours for the charts (Tailwind amber, teal, sky and violet).
     */
    private const AMBER = '#f59e0b';

    private const AMBER_LIGHT = 'rgba(245, 158, 11, 0.35)';

    private const PAYMENT_COLOURS = [
        'cash' => '#f59e0b',
        'upi' => '#14b8a6',
        'razorpay' => '#0ea5e9',
    ];

    /**
     * Every paid bill from the last 7 days (today included).
     *
     * One query feeds the cards and three of the charts, which then group the
     * rows in PHP. That keeps the SQL the same on MySQL and on SQLite (tests).
     *
     * SELECT paid_at, total_amount, payment_method FROM orders
     * WHERE status = 'paid' AND paid_at >= '<6 days ago, 00:00>'
     *
     * @return Collection<int, Order>
     */
    #[Computed]
    public function weekOrders(): Collection
    {
        return Order::where('status', 'paid')
            ->where('paid_at', '>=', today()->subDays(6))
            ->get(['paid_at', 'total_amount', 'payment_method']);
    }

    /**
     * The numbers on the four cards at the top.
     *
     * @return array{todaySales: float, salesChange: ?float, billsToday: int, averageBill: float, freeTables: int, totalTables: int, openBills: int}
     */
    #[Computed]
    public function stats(): array
    {
        $today = $this->weekOrders->filter(fn (Order $order) => $order->paid_at->isToday());
        $yesterdaySales = (float) $this->weekOrders
            ->filter(fn (Order $order) => $order->paid_at->isYesterday())
            ->sum('total_amount');

        $todaySales = (float) $today->sum('total_amount');

        return [
            'todaySales' => $todaySales,
            // Percent up or down on yesterday. Nothing sold yesterday means there is nothing to compare.
            'salesChange' => $yesterdaySales > 0 ? round(($todaySales - $yesterdaySales) / $yesterdaySales * 100) : null,
            'billsToday' => $today->count(),
            'averageBill' => $today->isEmpty() ? 0 : round($todaySales / $today->count(), 2),
            'freeTables' => CafeTable::where('is_active', true)->where('status', 'available')->count(),
            'totalTables' => CafeTable::where('is_active', true)->count(),
            'openBills' => Order::where('status', 'open')->count(),
        ];
    }

    /**
     * Sales for each of the last 7 days. Days with no sales still get a bar (₹0).
     *
     * @return array{labels: array<int, string>, totals: array<int, float>}
     */
    #[Computed]
    public function dailySales(): array
    {
        $totalsByDay = $this->weekOrders
            ->groupBy(fn (Order $order) => $order->paid_at->toDateString())
            ->map(fn (Collection $orders) => round((float) $orders->sum('total_amount'), 2));

        $days = collect(range(6, 0))->map(fn (int $daysAgo) => today()->subDays($daysAgo));

        return [
            'labels' => $days->map(fn (CarbonInterface $day) => $day->isToday() ? 'Today' : $day->format('D j'))->all(),
            'totals' => $days->map(fn (CarbonInterface $day) => $totalsByDay[$day->toDateString()] ?? 0)->all(),
        ];
    }

    /**
     * Today's sales hour by hour, from 8 AM to 9 PM (wider if a bill was paid outside those hours).
     *
     * @return array{labels: array<int, string>, totals: array<int, float>}
     */
    #[Computed]
    public function hourlySales(): array
    {
        $totalsByHour = $this->weekOrders
            ->filter(fn (Order $order) => $order->paid_at->isToday())
            ->groupBy(fn (Order $order) => $order->paid_at->hour)
            ->map(fn (Collection $orders) => round((float) $orders->sum('total_amount'), 2));

        $hours = range(min(8, $totalsByHour->keys()->min() ?? 8), max(21, $totalsByHour->keys()->max() ?? 21));

        return [
            'labels' => array_map(fn (int $hour) => today()->setHour($hour)->format('g A'), $hours),
            'totals' => array_map(fn (int $hour) => $totalsByHour[$hour] ?? 0, $hours),
        ];
    }

    /**
     * How the last 7 days were paid: cash, UPI or Razorpay.
     *
     * @return array{labels: array<int, string>, totals: array<int, float>, colours: array<int, string>}
     */
    #[Computed]
    public function paymentMethods(): array
    {
        $totals = $this->weekOrders
            ->groupBy('payment_method')
            ->map(fn (Collection $orders) => round((float) $orders->sum('total_amount'), 2))
            ->sortDesc();

        return [
            'labels' => $totals->keys()->map(fn (?string $method) => $method === 'upi' ? 'UPI' : ucfirst($method ?? 'Other'))->all(),
            'totals' => $totals->values()->all(),
            'colours' => $totals->keys()->map(fn (?string $method) => self::PAYMENT_COLOURS[$method] ?? '#8b5cf6')->all(),
        ];
    }

    /**
     * The 5 items sold most in the last 7 days, counted by quantity.
     *
     * SELECT item_name, SUM(quantity) AS quantity FROM order_items
     * JOIN orders ON orders.id = order_items.order_id
     * WHERE orders.status = 'paid' AND orders.paid_at >= '<6 days ago>' AND order_items.status != 'cancelled'
     * GROUP BY item_name ORDER BY quantity DESC LIMIT 5
     *
     * @return Collection<int, OrderItem>
     */
    #[Computed]
    public function topItems(): Collection
    {
        return OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'paid')
            ->where('orders.paid_at', '>=', today()->subDays(6))
            ->where('order_items.status', '!=', 'cancelled')
            ->selectRaw('order_items.item_name, SUM(order_items.quantity) as quantity')
            ->groupBy('order_items.item_name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get();
    }

    /**
     * The last 5 bills paid, newest first.
     *
     * @return Collection<int, Order>
     */
    #[Computed]
    public function recentBills(): Collection
    {
        return Order::with('cafeTable')
            ->where('status', 'paid')
            ->latest('paid_at')
            ->limit(5)
            ->get();
    }

    /**
     * Everything each chart needs, handed to dashboardChart() in resources/js/app.js.
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function charts(): array
    {
        $daily = $this->dailySales;
        $hourly = $this->hourlySales;
        $payments = $this->paymentMethods;

        return [
            'daily' => [
                'type' => 'bar',
                'labels' => $daily['labels'],
                'datasets' => [[
                    'label' => 'Sales',
                    'data' => $daily['totals'],
                    // Today's bar is solid amber; earlier days are lighter.
                    'backgroundColor' => array_map(fn (int $index) => $index === 6 ? self::AMBER : self::AMBER_LIGHT, array_keys($daily['totals'])),
                    'borderRadius' => 6,
                    'maxBarThickness' => 48,
                ]],
            ],
            'hourly' => [
                'type' => 'line',
                'labels' => $hourly['labels'],
                'datasets' => [[
                    'label' => 'Sales',
                    'data' => $hourly['totals'],
                    'borderColor' => self::AMBER,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'pointBackgroundColor' => self::AMBER,
                    'fill' => true,
                    'tension' => 0.35,
                ]],
            ],
            'payments' => [
                'type' => 'doughnut',
                'labels' => $payments['labels'],
                'datasets' => [[
                    'label' => 'Sales',
                    'data' => $payments['totals'],
                    'backgroundColor' => $payments['colours'],
                    'borderWidth' => 0,
                    'hoverOffset' => 6,
                ]],
            ],
            'topItems' => [
                'type' => 'bar',
                'horizontal' => true,
                'money' => false,
                'labels' => $this->topItems->pluck('item_name')->all(),
                'datasets' => [[
                    'label' => 'Sold',
                    'data' => $this->topItems->map(fn (OrderItem $item) => (int) $item->quantity)->all(),
                    'backgroundColor' => '#14b8a6',
                    'borderRadius' => 6,
                    'maxBarThickness' => 28,
                ]],
            ],
        ];
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6">
    {{-- Greeting --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Welcome back, {{ auth()->user()->name }}</flux:heading>
            <flux:text class="mt-1">
                @role('admin') Administrator @else Manager @endrole
                · {{ now()->format('l, j F Y') }}
            </flux:text>
        </div>
        <flux:button href="{{ route('select-table') }}" variant="primary" icon="receipt-percent">
            Take order
        </flux:button>
    </div>

    {{-- Today at a glance --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="col-span-2 rounded-xl bg-linear-to-r from-amber-500 to-orange-500 p-5 text-white shadow-sm lg:col-span-1">
            <div class="flex items-center justify-between">
                <p class="text-sm text-white/80">Today's sales</p>
                <flux:icon.currency-rupee class="size-5 text-white/80" />
            </div>
            <p class="mt-2 text-2xl font-bold tabular-nums">₹{{ number_format($this->stats['todaySales'], 2) }}</p>
            @if($this->stats['salesChange'] !== null)
                <p class="mt-1 flex items-center gap-1 text-xs text-white/90">
                    <flux:icon :icon="$this->stats['salesChange'] >= 0 ? 'arrow-trending-up' : 'arrow-trending-down'" variant="micro" />
                    {{ $this->stats['salesChange'] >= 0 ? '+' : '' }}{{ $this->stats['salesChange'] }}% on yesterday
                </p>
            @else
                <p class="mt-1 text-xs text-white/80">No sales yesterday to compare</p>
            @endif
        </div>

        @foreach ([
            ['label' => 'Bills paid today', 'value' => number_format($this->stats['billsToday']), 'icon' => 'document-check', 'hint' => 'Cash, UPI and Razorpay'],
            ['label' => 'Average bill', 'value' => '₹'.number_format($this->stats['averageBill'], 2), 'icon' => 'calculator', 'hint' => 'Today'],
            ['label' => 'Free tables', 'value' => $this->stats['freeTables'].' / '.$this->stats['totalTables'], 'icon' => 'squares-2x2', 'hint' => $this->stats['openBills'].' '.Str::plural('bill', $this->stats['openBills']).' still open'],
        ] as $card)
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <flux:text class="text-sm">{{ $card['label'] }}</flux:text>
                    <div class="flex size-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                        <flux:icon :icon="$card['icon']" class="size-4" />
                    </div>
                </div>
                <p class="mt-2 text-2xl font-bold tabular-nums text-stone-800 dark:text-zinc-100">{{ $card['value'] }}</p>
                <flux:text class="mt-1 text-xs">{{ $card['hint'] }}</flux:text>
            </div>
        @endforeach
    </div>

    {{--
        Charts. wire:ignore stops Livewire from redrawing the canvas, and
        dashboardChart() (resources/js/app.js) draws each one from the data in charts().
    --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-baseline justify-between">
                <flux:heading size="lg">Sales · last 7 days</flux:heading>
                <span class="text-sm font-semibold tabular-nums text-amber-600 dark:text-amber-400">₹{{ number_format(array_sum($this->dailySales['totals']), 2) }}</span>
            </div>
            <div
                wire:ignore
                x-data="{ chart: null, init() { this.chart = dashboardChart(this.$refs.canvas, @js($this->charts['daily'])) }, destroy() { this.chart?.destroy() } }"
                class="relative mt-4 h-72"
            >
                <canvas x-ref="canvas" aria-label="Sales for each of the last 7 days" role="img"></canvas>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Payment methods</flux:heading>
            <flux:text class="text-sm">Last 7 days</flux:text>
            @if(empty($this->paymentMethods['totals']))
                <div class="flex h-72 items-center justify-center">
                    <flux:text>No payments yet.</flux:text>
                </div>
            @else
                <div
                    wire:ignore
                    x-data="{ chart: null, init() { this.chart = dashboardChart(this.$refs.canvas, @js($this->charts['payments'])) }, destroy() { this.chart?.destroy() } }"
                    class="relative mt-4 h-64"
                >
                    <canvas x-ref="canvas" aria-label="Sales by payment method" role="img"></canvas>
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Today by hour</flux:heading>
            <flux:text class="text-sm">When bills were paid today</flux:text>
            <div
                wire:ignore
                x-data="{ chart: null, init() { this.chart = dashboardChart(this.$refs.canvas, @js($this->charts['hourly'])) }, destroy() { this.chart?.destroy() } }"
                class="relative mt-4 h-64"
            >
                <canvas x-ref="canvas" aria-label="Today's sales by hour" role="img"></canvas>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Top items</flux:heading>
            <flux:text class="text-sm">Most sold · last 7 days</flux:text>
            @if($this->topItems->isEmpty())
                <div class="flex h-64 items-center justify-center">
                    <flux:text>Nothing sold yet.</flux:text>
                </div>
            @else
                <div
                    wire:ignore
                    x-data="{ chart: null, init() { this.chart = dashboardChart(this.$refs.canvas, @js($this->charts['topItems'])) }, destroy() { this.chart?.destroy() } }"
                    class="relative mt-4 h-64"
                >
                    <canvas x-ref="canvas" aria-label="Top 5 items sold" role="img"></canvas>
                </div>
            @endif
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Recent bills --}}
        <section class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xs lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4 dark:border-zinc-800">
                <flux:heading size="lg">Recent bills</flux:heading>
                @role('admin')
                    <flux:button href="{{ route('sale-report') }}" size="sm" variant="ghost" icon-trailing="arrow-right">Sale report</flux:button>
                @endrole
            </div>
            <ul class="divide-y divide-stone-100 dark:divide-zinc-800">
                @forelse($this->recentBills as $bill)
                    <li wire:key="bill-{{ $bill->id }}">
                        <a href="{{ route('checkout.success', $bill) }}" class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-stone-50 dark:hover:bg-zinc-800/50">
                            <div class="min-w-0">
                                <p class="font-medium text-stone-800 dark:text-zinc-100">
                                    Table {{ $bill->cafeTable?->name }}
                                    <span class="text-sm font-normal text-stone-500 dark:text-zinc-400">· #{{ $bill->order_number }}</span>
                                </p>
                                <p class="text-xs text-stone-500 dark:text-zinc-400">{{ $bill->paid_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <flux:badge size="sm" color="{{ ['cash' => 'amber', 'upi' => 'teal', 'razorpay' => 'sky'][$bill->payment_method] ?? 'zinc' }}">
                                    {{ $bill->payment_method === 'upi' ? 'UPI' : ucfirst($bill->payment_method ?? '—') }}
                                </flux:badge>
                                <span class="font-semibold tabular-nums">₹{{ number_format((float) $bill->total_amount, 2) }}</span>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center">
                        <flux:text>No bills paid yet.</flux:text>
                    </li>
                @endforelse
            </ul>
        </section>

        {{-- Quick links. Only admins can open the management pages. --}}
        <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">Quick links</flux:heading>
            <div class="mt-4 space-y-2">
                @foreach (array_filter([
                    ['route' => 'select-table', 'icon' => 'receipt-percent', 'title' => 'Take Order', 'label' => 'Pick a table and start an order.', 'admin' => false],
                    ['route' => 'category-management', 'icon' => 'tag', 'title' => 'Categories', 'label' => 'Group your menu into categories.', 'admin' => true],
                    ['route' => 'menu-management', 'icon' => 'book-open', 'title' => 'Menu Items', 'label' => 'Add dishes, drinks and prices.', 'admin' => true],
                    ['route' => 'table-management', 'icon' => 'squares-2x2', 'title' => 'Tables', 'label' => 'Set up the tables in your cafe.', 'admin' => true],
                ], fn (array $link) => ! $link['admin'] || auth()->user()->hasRole('admin')) as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        class="group flex items-center gap-3 rounded-lg p-2 transition hover:bg-amber-50 dark:hover:bg-amber-500/10"
                    >
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 transition group-hover:bg-amber-500 group-hover:text-white dark:bg-amber-500/10 dark:text-amber-400">
                            <flux:icon :icon="$link['icon']" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-stone-800 dark:text-zinc-100">{{ $link['title'] }}</p>
                            <p class="truncate text-xs text-stone-500 dark:text-zinc-400">{{ $link['label'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</div>
