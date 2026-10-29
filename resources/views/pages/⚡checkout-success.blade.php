<?php

use App\Models\Order;
use Livewire\Component;

new class extends Component
{
    // The paid order from the URL: /checkout/{order}/success.
    // Livewire finds that row and passes it into mount() below.
    public Order $order;

    // Cash received and the change to hand back.
    // Checkout flashes this for one request only. UPI and Razorpay leave it empty.
    public ?array $cash = null;

    public function mount(Order $order): void
    {
        // This page is only for a finished payment. Anything else is a 404.
        abort_unless($order->status === 'paid', 404);

        // Load the table name and the lines the guest paid for.
        // Cancelled lines stay in the database, but they are not on the receipt.
        $this->order = $order->load([
            'cafeTable',
            'orderItems' => fn ($query) => $query->where('status', '!=', 'cancelled')->orderBy('id'),
        ]);

        // session('cash') is set in checkout just before the redirect, then forgotten.
        // Refreshing this page will not show the change again.
        $this->cash = session('cash');
    }
};
?>

{{-- $order and $cash are the public properties filled in mount(). --}}
<div class="mx-auto max-w-2xl space-y-6">
    <div class="rounded-xl bg-linear-to-r from-emerald-500 to-teal-500 p-6 text-center text-white shadow-sm">
        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-white/20">
            <flux:icon.check-circle class="size-8" />
        </div>
        <p class="mt-3 text-sm text-white/80">Payment successful</p>
        <p class="text-3xl font-bold tabular-nums">₹{{ number_format((float) $order->total_amount, 2) }}</p>
        <p class="mt-1 text-sm text-white/80">Table {{ $order->cafeTable?->name }} is free.</p>
    </div>

    {{-- Only a cash payment flashed a received amount and change. --}}
    @if($cash)
        <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-5 py-4 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
            <span class="text-sm">Received ₹{{ number_format($cash['received'], 2) }}</span>
            <span class="text-lg font-bold tabular-nums">Change ₹{{ number_format($cash['change'], 2) }}</span>
        </div>
    @endif

    <section class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        {{-- Bill number, how they paid, who the guest was, and when. --}}
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-b border-stone-200 px-5 py-4 text-sm dark:border-zinc-800">
            <div>
                <dt class="text-stone-500 dark:text-zinc-400">Bill no.</dt>
                <dd class="font-medium tabular-nums">#{{ $order->order_number }}</dd>
            </div>
            <div>
                <dt class="text-stone-500 dark:text-zinc-400">Payment</dt>
                {{-- ucfirst('razorpay') becomes 'Razorpay'. UPI must stay all capitals. --}}
                <dd class="font-medium">{{ $order->payment_method === 'upi' ? 'UPI' : ucfirst($order->payment_method) }}</dd>
            </div>
            @if($order->customer_name)
                <div>
                    <dt class="text-stone-500 dark:text-zinc-400">Guest</dt>
                    <dd class="font-medium">{{ $order->customer_name }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-stone-500 dark:text-zinc-400">Paid at</dt>
                <dd class="font-medium">{{ $order->paid_at?->format('d M Y, h:i A') }}</dd>
            </div>
            {{-- Saved when Razorpay's signature was checked. Cash and UPI do not have one. --}}
            @if($order->razorpay_payment_id)
                <div class="col-span-2">
                    <dt class="text-stone-500 dark:text-zinc-400">Razorpay reference</dt>
                    <dd class="font-medium break-all">{{ $order->razorpay_payment_id }}</dd>
                </div>
            @endif
        </dl>

        {{-- One row per item that was paid for. wire:key keeps each row stable if the page redraws. --}}
        <ul class="divide-y divide-stone-100 dark:divide-zinc-800">
            @foreach($order->orderItems as $item)
                <li wire:key="receipt-{{ $item->id }}" class="flex justify-between gap-4 px-5 py-3">
                    <span><span class="tabular-nums">{{ $item->quantity }}×</span> {{ $item->item_name }}</span>
                    <span class="shrink-0 tabular-nums">₹{{ number_format((float) $item->subtotal, 2) }}</span>
                </li>
            @endforeach
        </ul>

        <div class="space-y-2 border-t border-dashed border-stone-300 bg-stone-50 px-5 py-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/40">
            @if((float) $order->tax_amount > 0 || (float) $order->discount_amount > 0)
                <div class="flex justify-between">
                    <flux:text>Subtotal</flux:text>
                    <span class="tabular-nums">₹{{ number_format((float) $order->subtotal, 2) }}</span>
                </div>
            @endif
            @if((float) $order->tax_amount > 0)
                <div class="flex justify-between">
                    <flux:text>Tax</flux:text>
                    <span class="tabular-nums">₹{{ number_format((float) $order->tax_amount, 2) }}</span>
                </div>
            @endif
            @if((float) $order->discount_amount > 0)
                <div class="flex justify-between">
                    <flux:text>Discount</flux:text>
                    <span class="tabular-nums text-emerald-600 dark:text-emerald-400">−₹{{ number_format((float) $order->discount_amount, 2) }}</span>
                </div>
            @endif
            <div class="flex items-end justify-between pt-1 text-lg font-bold">
                <span>Total paid</span>
                <span class="tabular-nums text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $order->total_amount, 2) }}</span>
            </div>
        </div>
    </section>

    <div class="flex flex-wrap gap-3">
        {{-- A normal link, not wire:navigate. wire:navigate would try to show the PDF inside the app. --}}
        <flux:button href="{{ route('orders.receipt', $order) }}" variant="primary" icon="arrow-down-tray" class="flex-1">
            Download receipt
        </flux:button>
        <flux:button href="{{ route('select-table') }}" icon="squares-2x2" class="flex-1">
            Back to tables
        </flux:button>
    </div>
</div>
