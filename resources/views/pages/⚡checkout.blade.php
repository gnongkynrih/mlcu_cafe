<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RazorpayGateway;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Razorpay\Api\Errors\BadRequestError;
use Razorpay\Api\Errors\SignatureVerificationError;

new class extends Component
{
    // The open order stored by Select Table. The bill itself is always
    // read from the database, never from values the browser can edit.
    public $info = [];
    public $billItems = [];
    public $paymentMethod = 'cash';

    public $amountReceived = '';

    // True while the Razorpay window is open, so Take payment cannot run twice.
    public bool $awaitingRazorpay = false;

    // Re-injected on every request. Not a public property, so it is not sent to the browser.
    protected RazorpayGateway $razorpay;

    public function boot(RazorpayGateway $razorpay): void
    {
        $this->razorpay = $razorpay;
    }

    public function mount(): void
    {
        $this->info = session('order');

        if (! $this->info || ! Order::where('id', $this->info['order_id'])->where('status', 'open')->exists()) {
            session()->forget('order');

            // Stop here. There is no bill to draw, and the next lines would
            // read the missing order.
            $this->redirect(route('select-table'));

            return;
        }

        $this->billItems = $this->getBillItems();
    }

    /**
     * Items the guest still has to pay for. Cancelled lines stay in the
     * database, but they are not part of the bill.
     */
    
    public function getBillItems()
    {
        return OrderItem::where('order_id', $this->info['order_id'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{subtotal: float, tax: float, discount: float, total: float}
     */
    #[Computed]
    public function bill(): array
    {
        return $this->billFor($this->order);
    }

    #[Computed]
    public function order(): Order
    {
        return Order::where('status', 'open')->findOrFail($this->info['order_id']);
    }

    /**
     * Cash needs an amount that covers the bill. UPI and Razorpay are taken
     * for the exact total, so no amount is asked.
     */
    #[Computed]
    public function canPay(): bool
    {
        if ($this->billItems->isEmpty()) {
            return false;
        }

        if ($this->paymentMethod !== 'cash' || $this->bill['total'] <= 0) {
            return true;
        }

        return is_numeric($this->amountReceived)
            && round((float) $this->amountReceived, 2) >= $this->bill['total'];
    }

    #[Computed]
    public function changeDue(): float
    {
        if ($this->paymentMethod !== 'cash' || ! is_numeric($this->amountReceived)) {
            return 0;
        }

        return round(max(0, (float) $this->amountReceived - $this->bill['total']), 2);
    }

    public function pay(): void
    {
        $order = Order::where('status', 'open')->find($this->info['order_id'] ?? null);

        if (! $order) {
            session()->forget('order');
            $this->redirect(route('select-table'));

            return;
        }

        if ($order->orderItems()->where('status', '!=', 'cancelled')->doesntExist()) {
            return;
        }

        $bill = $this->billFor($order);

        $this->validate([
            'paymentMethod' => ['required', 'in:cash,razorpay,upi'],
            'amountReceived' => $this->paymentMethod === 'cash' && $bill['total'] > 0
                ? ['bail', 'required', 'numeric', 'min:'.number_format($bill['total'], 2, '.', '')]
                : ['nullable'],
        ], [
            'paymentMethod.required' => 'Choose how the guest is paying.',
            'paymentMethod.in' => 'Choose cash, RazorPay, or UPI.',
            'amountReceived.required' => 'Enter the amount received.',
            'amountReceived.numeric' => 'Amount received must be a number.',
            'amountReceived.min' => 'Amount received must cover the bill of ₹'.number_format($bill['total'], 2).'.',
        ]);

        // Razorpay is not collected here. A ₹0 bill has nothing to charge, so it
        // skips the payment window and is recorded like cash.
        if ($this->paymentMethod === 'razorpay' && $bill['total'] > 0) {
            $this->startRazorpay($order, $bill);

            return;
        }

        $this->recordPayment($order, $bill);
    }

    /**
     * Step 1 of a Razorpay payment: create the order, then open the window.
     *
     * The bill total is read from the database in paise (₹1 = 100 paise).
     * The browser cannot send its own price. The table stays occupied until
     * confirmRazorpay() checks the signature.
     */
    protected function startRazorpay(Order $order, array $bill): void
    {
        // Same values the gateway sends. env() is read in config/services.php,
        // because a cached config file would ignore the .env file.
        if (blank(config('services.razorpay.key')) || blank(config('services.razorpay.secret'))) {
            $this->addError('paymentMethod', 'Razorpay is not set up yet. Add RAZOR_KEY and RAZOR_SECRET to the .env file.');

            return;
        }

        $amountInPaise = (int) round($bill['total'] * 100);

        // Razorpay refuses anything under ₹1. A tiny leftover is easier to take in cash.
        if ($amountInPaise < 100) {
            $this->addError('paymentMethod', 'Razorpay cannot collect less than ₹1.00. Take this bill in cash.');

            return;
        }
        // Create the Razorpay order with the key id and secret from config.
        // An empty secret makes Razorpay answer "Authentication failed".
        try {
            $razorpayOrderId = $this->razorpay->createOrder($amountInPaise, (string) $order->order_number);
        } catch (BadRequestError) {
            $this->addError('paymentMethod', 'Razorpay refused these API keys. Check RAZOR_KEY and RAZOR_SECRET in the .env file.');

            return;
        }

        // Remember this id. The callback is accepted only when it names the same one,
        // so a payment made for a cheaper order cannot close this table.
        $order->update(['razorpay_order_id' => $razorpayOrderId]);

        $this->awaitingRazorpay = true;

        // The key id is public. The secret is not included in this event.
        $this->dispatch(
            'open-razorpay',
            key: (string) config('services.razorpay.key'),
            amount: $amountInPaise,
            currency: 'INR',
            name: (string) config('app.name'),
            description: 'Table '.$this->info['table_name'].' · #'.$order->order_number,
            orderId: $razorpayOrderId,
            // prefillName: (string) ($this->info['customer_name'] ?? ''),
        );
    }

    /**
     * Step 2 of a Razorpay payment. The window sends three values back.
     * They are trusted only after the signature matches the secret.
     */
    public function confirmRazorpay(string $paymentId, string $orderId, string $signature): void
    {
        $order = Order::where('status', 'open')->find($this->info['order_id'] ?? null);

        if (! $order) {
            session()->forget('order');
            $this->redirect(route('select-table'));

            return;
        }

        if ($order->razorpay_order_id === null || $order->razorpay_order_id !== $orderId) {
            $this->awaitingRazorpay = false;
            $this->addError('paymentMethod', 'This payment does not match the open bill.');

            return;
        }

        try {
            $this->razorpay->verifyPayment($orderId, $paymentId, $signature);
        } catch (SignatureVerificationError) {
            $this->awaitingRazorpay = false;
            $this->addError('paymentMethod', 'Razorpay could not confirm this payment. The table is still open.');

            return;
        }

        // Ignore a radio change that happened while the window was open.
        $this->paymentMethod = 'razorpay';
        $this->recordPayment($order, $this->billFor($order), $paymentId);
    }

    /**
     * The cashier closed the Razorpay window without paying.
     * The bill stays open so they can try again or take cash.
     */
    public function razorpayDismissed(): void
    {
        $this->awaitingRazorpay = false;
    }

    /**
     * Mark the bill paid and free the table. Cash, UPI, and a verified
     * Razorpay payment all finish here.
     */
    protected function recordPayment(Order $order, array $bill, ?string $razorpayPaymentId = null): void
    {
        DB::transaction(function () use ($order, $bill, $razorpayPaymentId) {
            $attributes = [
                'subtotal' => $bill['subtotal'],
                'total_amount' => $bill['total'],
                'status' => 'paid',
                'payment_method' => $this->paymentMethod,
                'billed_at' => now(),
                'paid_at' => now(),
            ];

            if ($razorpayPaymentId !== null) {
                $attributes['razorpay_payment_id'] = $razorpayPaymentId;
            }

            $order->update($attributes);

            // The guests are leaving, so the table can be seated again.
            $order->cafeTable()->update(['status' => 'available']);
        });

        session()->forget('order');
        session()->flash('status', 'Payment received for table '.$this->info['table_name'].'. The table is free.');

        $this->redirect(route('select-table'));
    }

    /**
     * Recalculate from the saved rows. Tax and discount already live on
     * the order; this page does not let the browser send a new total.
     *
     * @return array{subtotal: float, tax: float, discount: float, total: float}
     */
    protected function billFor(Order $order): array
    {
        $subtotal = round((float) $order->orderItems()->where('status', '!=', 'cancelled')->sum('subtotal'), 2);
        $tax = round((float) $order->tax_amount, 2);
        $discount = round((float) $order->discount_amount, 2);

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => round(max(0, $subtotal + $tax - $discount), 2),
        ];
    }
};
?>

<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-linear-to-r from-amber-500 to-orange-500 p-5 text-white shadow-sm">
        <div class="flex items-center gap-4">
            <div class="flex size-12 items-center justify-center rounded-lg bg-white/20">
                <flux:icon.currency-rupee class="size-6" />
            </div>
            <div>
                <p class="text-sm text-white/80">Checkout · {{ $info['table_name'] }}</p>
                <p class="text-2xl font-bold leading-tight">₹{{ number_format($this->bill['total'], 2) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            @if($info['customer_name'])
                <span class="flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1">
                    <flux:icon.user variant="micro" /> {{ $info['customer_name'] }}
                </span>
            @endif
            <span class="flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1">
                <flux:icon.users variant="micro" /> {{ $info['guests'] }} {{ Str::plural('guest', $info['guests']) }}
            </span>
            <span class="rounded-full bg-white/20 px-3 py-1 font-medium tabular-nums">#{{ $this->order->order_number }}</span>
            <flux:button href="{{ route('take-order') }}" size="sm" variant="ghost" icon="arrow-left" class="text-white! hover:bg-white/20!">
                Back to order
            </flux:button>
        </div>
    </div>

    @if($this->billItems->isEmpty())
        <flux:callout
            variant="warning"
            icon="exclamation-triangle"
            heading="Nothing to bill yet"
            text="Send at least one item to the kitchen, then come back to take payment."
        />
    @else
        <div class="grid items-start gap-6 lg:grid-cols-5">
            <section class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-xs lg:col-span-3 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center gap-2 border-b border-stone-200 px-5 py-4 dark:border-zinc-800">
                    <flux:icon.receipt-percent class="size-5 text-amber-500" />
                    <flux:heading size="lg">Bill</flux:heading>
                </div>

                <ul class="divide-y divide-stone-100 dark:divide-zinc-800">
                    @foreach($this->billItems as $item)
                        <li wire:key="bill-{{ $item->id }}" class="flex items-start justify-between gap-4 px-5 py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-stone-800 dark:text-zinc-100">
                                    <span class="tabular-nums">{{ $item->quantity }}×</span>
                                    {{ $item->item_name }}
                                    @if($item->status !== 'pending')
                                        <flux:badge size="sm" color="sky" class="ml-1">{{ ucfirst($item->status) }}</flux:badge>
                                    @endif
                                </p>
                                @if($item->note)
                                    <p class="truncate text-xs italic text-stone-500 dark:text-zinc-400">“{{ $item->note }}”</p>
                                @endif
                            </div>
                            <p class="shrink-0 font-medium tabular-nums">₹{{ number_format((float) $item->subtotal, 2) }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="space-y-2 border-t border-dashed border-stone-300 bg-stone-50 px-5 py-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/40">
                    @if($this->bill['tax'] > 0 || $this->bill['discount'] > 0)
                        <div class="flex justify-between">
                            <flux:text>Subtotal</flux:text>
                            <span class="tabular-nums">₹{{ number_format($this->bill['subtotal'], 2) }}</span>
                        </div>
                    @endif
                    @if($this->bill['tax'] > 0)
                        <div class="flex justify-between">
                            <flux:text>Tax</flux:text>
                            <span class="tabular-nums">₹{{ number_format($this->bill['tax'], 2) }}</span>
                        </div>
                    @endif
                    @if($this->bill['discount'] > 0)
                        <div class="flex justify-between">
                            <flux:text>Discount</flux:text>
                            <span class="tabular-nums text-emerald-600 dark:text-emerald-400">−₹{{ number_format($this->bill['discount'], 2) }}</span>
                        </div>
                    @endif
                    <div class="flex items-end justify-between pt-1 text-lg font-bold">
                        <span>Total</span>
                        <span class="tabular-nums text-amber-600 dark:text-amber-400">₹{{ number_format($this->bill['total'], 2) }}</span>
                    </div>
                </div>
            </section>

            <aside class="rounded-xl border border-stone-200 bg-white p-5 shadow-xs lg:sticky lg:top-6 lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">Payment</flux:heading>
                <flux:text class="mt-1">How is the guest paying?</flux:text>

                <div class="mt-4 space-y-4">
                    <flux:radio.group wire:model.live="paymentMethod" variant="segmented">
                        <flux:radio value="cash" label="Cash" icon="banknotes" />
                        <flux:radio value="upi" label="UPI" icon="device-phone-mobile" />
                        <flux:radio value="razorpay" label="Razorpay" icon="credit-card" />
                    </flux:radio.group>
                    <flux:error name="paymentMethod" />

                    @if($paymentMethod === 'razorpay' && $this->bill['total'] > 0)
                        <flux:text class="text-sm">
                            @if($awaitingRazorpay)
                                Waiting for Razorpay. Close that window to choose another way to pay.
                            @else
                                A Razorpay window will open. The table stays occupied until that payment succeeds.
                            @endif
                        </flux:text>
                    @endif

                    @if($paymentMethod === 'cash' && $this->bill['total'] > 0)
                        <flux:field>
                            <flux:label>Amount received</flux:label>
                            <flux:input
                                type="number"
                                inputmode="decimal"
                                step="0.01"
                                min="0"
                                icon="currency-rupee"
                                placeholder="0.00"
                                wire:model.live="amountReceived"
                            />
                            <flux:error name="amountReceived" />
                        </flux:field>

                        @if(is_numeric($amountReceived) && round((float) $amountReceived, 2) >= $this->bill['total'])
                            <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-3 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <span class="text-sm font-medium">Change</span>
                                <span class="text-lg font-bold tabular-nums">₹{{ number_format($this->changeDue, 2) }}</span>
                            </div>
                        @elseif(is_numeric($amountReceived))
                            <flux:text class="text-sm text-red-600 dark:text-red-400">
                                ₹{{ number_format($this->bill['total'] - (float) $amountReceived, 2) }} still due
                            </flux:text>
                        @endif
                    @endif

                    <flux:button
                        variant="primary"
                        icon="check"
                        class="w-full"
                        wire:click="pay"
                        wire:loading.attr="disabled"
                        wire:target="pay,confirmRazorpay"
                        :disabled="! $this->canPay || $awaitingRazorpay"
                    >
                        {{ $paymentMethod === 'razorpay' ? 'Pay with Razorpay' : 'Take payment' }}
                    </flux:button>
                </div>
            </aside>
        </div>
    @endif
</div>

{{-- Razorpay's own script. @assets loads it once, even if this page is visited again. --}}
@assets
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endassets

@script
<script>
    // pay() dispatches this after the server has created a Razorpay order.
    // The payload has the public key id only. The secret never arrives here.
    $wire.on('open-razorpay', (payment) => {
        const checkout = new Razorpay({
            key: payment.key,
            amount: payment.amount,
            currency: payment.currency,
            name: payment.name,
            description: payment.description,
            order_id: payment.orderId,
            prefill: {
                name: payment.prefillName,
            },
            theme: {
                color: '#4d23e6',
            },
            // Razorpay calls handler only after the guest pays.
            // Closing the table still happens on the server, after the signature check.
            handler(response) {
                $wire.confirmRazorpay(
                    response.razorpay_payment_id,
                    response.razorpay_order_id,
                    response.razorpay_signature,
                );
            },
            modal: {
                ondismiss() {
                    $wire.razorpayDismissed();
                },
            },
        });

        checkout.open();
    });
</script>
@endscript
