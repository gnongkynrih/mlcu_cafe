<?php

use Livewire\Component;
use Illuminate\Support\Carbon; //for date format
use App\Models\Order;

new class extends Component
{
    public $dateFrom,$dateTo;
    public $orders;
    public $reportType = 'summary';
    public function mount(){
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }
    
    public function generateReport(){
       $this->orders = Order::selectRaw('SUM(total_amount) as total_amount, DATE(paid_at) as paid_at')
        ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$this->dateFrom, $this->dateTo])
        ->groupByRaw('DATE(paid_at)')
        ->get();
        $this->reportType='summary';
    }
    public function generateDetailsReport(){
        $this->orders = Order::select('total_amount','razorpay_payment_id','paid_at',)
        ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$this->dateFrom, $this->dateTo])
        ->orderBy('paid_at', 'asc')
        ->get();
        $this->reportType='details';
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading size="xl" level="1">Sale Report</flux:heading>
            @if($orders)
                <flux:badge color="amber" size="sm">{{ $reportType === 'summary' ? 'Summary' : 'Detail' }}</flux:badge>
            @endif
        </div>
        <flux:text class="mt-1">Paid bills between the dates you choose.</flux:text>
    </div>

    <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <flux:field class="w-full sm:w-48">
                <flux:label>From</flux:label>
                <flux:input type="date" wire:model="dateFrom" />
            </flux:field>
            <flux:field class="w-full sm:w-48">
                <flux:label>To</flux:label>
                <flux:input type="date" wire:model="dateTo" />
            </flux:field>
            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="generateReport" wire:loading.attr="disabled" wire:target="generateReport" variant="primary" icon="chart-bar">
                    Summary
                </flux:button>
                <flux:button wire:click="generateDetailsReport" wire:loading.attr="disabled" wire:target="generateDetailsReport" icon="list-bullet">
                    Detail
                </flux:button>
            </div>
        </div>
    </div>

    @if($orders === null)
        <flux:callout
            icon="chart-bar"
            heading="No report yet"
            text="Pick the dates, then choose Summary for one total per day or Detail for every payment."
        />
    @else
        @if($orders->isNotEmpty())
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-linear-to-r from-amber-500 to-orange-500 p-5 text-white shadow-sm">
            <div>
                <p class="text-sm text-white/80">
                    {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d M Y') }}
                    – {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d M Y') }}
                </p>
                <p class="text-2xl font-bold tabular-nums">₹{{ number_format((float) $orders->sum('total_amount'), 2) }}</p>
            </div>
            <span class="rounded-full bg-white/20 px-3 py-1 text-sm font-medium">
                {{ $orders->count() }} {{ Str::plural($reportType === 'summary' ? 'day' : 'payment', $orders->count()) }}
            </span>
        </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <div class="min-w-[640px]">
                    @if($reportType === 'summary')
                        <div class="grid grid-cols-12 gap-4 border-b border-stone-200 bg-stone-50 px-6 py-3 text-xs font-semibold tracking-wider text-stone-500 uppercase dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                            <div class="col-span-8">Date</div>
                            <div class="col-span-4 text-right">Total</div>
                        </div>
                        <div class="divide-y divide-stone-100 dark:divide-zinc-800">
                            @forelse($orders as $order)
                                <div wire:key="summary-{{ $loop->index }}" class="grid grid-cols-12 items-center gap-4 px-6 py-3">
                                    <p class="col-span-8 font-medium text-stone-800 dark:text-zinc-100">
                                        {{ $order->paid_at }}
                                    </p>
                                    <p class="col-span-4 text-right font-semibold tabular-nums text-stone-800 dark:text-zinc-100">
                                        ₹{{ number_format((float) $order->total_amount, 2) }}
                                    </p>
                                </div>
                            @empty
                                <div class="px-6 py-16 text-center">
                                    <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-500/10">
                                        <flux:icon.chart-bar class="size-7 text-amber-500" />
                                    </div>
                                    <flux:heading size="lg">No paid bills</flux:heading>
                                    <flux:text class="mt-1">Nothing was paid between these dates.</flux:text>
                                </div>
                            @endforelse
                        </div>
                    @else
                        <div class="grid grid-cols-12 gap-4 border-b border-stone-200 bg-stone-50 px-6 py-3 text-xs font-semibold tracking-wider text-stone-500 uppercase dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                            <div class="col-span-4">Paid at</div>
                            <div class="col-span-5">Razorpay reference</div>
                            <div class="col-span-3 text-right">Amount</div>
                        </div>
                        <div class="divide-y divide-stone-100 dark:divide-zinc-800">
                            @forelse($orders as $order)
                                <div wire:key="detail-{{ $loop->index }}" class="grid grid-cols-12 items-center gap-4 px-6 py-3">
                                    <p class="col-span-4 font-medium text-stone-800 dark:text-zinc-100">
                                        {{ $order->paid_at }}
                                    </p>
                                    <p class="col-span-5 truncate text-sm text-stone-500 dark:text-zinc-400">
                                        {{ $order->razorpay_payment_id ?: '—' }}
                                    </p>
                                    <p class="col-span-3 text-right font-semibold tabular-nums text-stone-800 dark:text-zinc-100">
                                        ₹{{ number_format((float) $order->total_amount, 2) }}
                                    </p>
                                </div>
                            @empty
                                <div class="px-6 py-16 text-center">
                                    <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-500/10">
                                        <flux:icon.chart-bar class="size-7 text-amber-500" />
                                    </div>
                                    <flux:heading size="lg">No paid bills</flux:heading>
                                    <flux:text class="mt-1">Nothing was paid between these dates.</flux:text>
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>