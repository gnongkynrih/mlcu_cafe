<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Flux\Flux;
new class extends Component
{
    public $info;
    public $categories = [];
    public $menuItems = [];

    // ---------------------------------------------------------------------
    // THE CART — items the waiter has picked but NOT yet sent to the kitchen.
    // It only lives in this component (in memory), not in the database.
    // Keyed by menu item id so the same item is never listed twice:
    //
    //   [
    //     5 => ['name' => 'Cappuccino', 'price' => 120, 'qty' => 2, 'note' => 'no sugar'],
    //     9 => ['name' => 'Brownie',    'price' => 80,  'qty' => 1, 'note' => ''],
    //   ]
    // ---------------------------------------------------------------------
    public $cart = [];

    // State for the two "cancel" pop-ups (modals)
    public $cancellingItemId = null;      // which order_items row we are cancelling
    public $cancelReason = '';            // reason typed by the waiter
    public $showCancelItemModal = false;
    public $showCancelOrderModal = false;

    public function mount(){
        //check if the session order is there or not
        if(!session('order')){
            return $this->redirect(route('select-table'));
        }
        //store the contents of the session in info
        $this->info = session()->get('order');

        // If this order was cancelled (or closed) in the meantime, start again
        if (! Order::where('id', $this->info['order_id'])->where('status', 'open')->exists()) {
            session()->forget('order');
            return $this->redirect(route('select-table'));
        }

        //get all the available food categories
        $this->categories = Category::select(['name','id'])->where('is_active',1)->orderBy('sort_order')->get()->toArray();
    }

    public function showMenuItems($categoryId = null){
        $sql = MenuItem::where('is_available',true);
        if($categoryId){
            $sql = $sql->where('category_id',$categoryId);
        }
        $this->menuItems = $sql->get();
    }

    // ---------------------------------------------------------------------
    // CART ACTIONS (called from the page with wire:click)
    // ---------------------------------------------------------------------

    // Tap on a menu card: add 1. If the item is already in the cart, qty + 1.
    public function addItem($menuId){
        // Always read the item from the DATABASE — never trust data sent by the browser.
        // findOrFail = 404 if the id does not exist (or the item is not available)
        $menu = MenuItem::where('is_available', true)->findOrFail($menuId);

        if (isset($this->cart[$menuId])) {
            $this->cart[$menuId]['qty']++; //if the food is already in the cart the increment it
        } else {
            $this->cart[$menuId] = [ //if the cart is empty
                'name'  => $menu->name,
                'price' => (float) $menu->price,
                'qty'   => 1,
                'note'  => '',
            ];
        }
    }

    public function increaseQty($menuId){
        if (isset($this->cart[$menuId])) {
            $this->cart[$menuId]['qty']++;
        }
    }

    // qty - 1. When it reaches 0 the line is removed from the cart.
    public function decreaseQty($menuId){
        if (! isset($this->cart[$menuId])) {
            return;
        }
        $this->cart[$menuId]['qty']--;

        if ($this->cart[$menuId]['qty'] < 1) {
            $this->removeItem($menuId);
        }
    }

    public function removeItem($menuId){
        //if the food is not in the cart, do nothing
        if (! isset($this->cart[$menuId])) {
            return;
        }
        // unset() deletes one key from an array
        unset($this->cart[$menuId]);

    }

    // ---------------------------------------------------------------------
    // COMPUTED PROPERTIES
    // #[Computed] turns a method into a read-only value you can use in the
    // view as $this->cartTotal. It is calculated once per request and is NOT
    // sent to the browser (unlike public properties).
    // ---------------------------------------------------------------------

    // Number of items in the cart, e.g. 2 cappuccinos + 1 brownie = 3
    #[Computed]
    public function cartCount(){
        return collect($this->cart)->sum('qty');
    }

    // Total price of the cart (price x qty for every line, added together)
    #[Computed]
    public function cartTotal(){
        return collect($this->cart)->sum(fn ($item) => $item['price'] * $item['qty']);
    }

    // Items of this order that were ALREADY sent to the kitchen earlier
    // SQL: SELECT * FROM order_items WHERE order_id = ? ORDER BY id
    #[Computed]
    public function sentItems(){
        return OrderItem::where('order_id', $this->info['order_id'])->orderBy('id')->get();
    }

    // Total of the sent items that are NOT cancelled
    #[Computed]
    public function sentTotal(){
        return $this->sentItems->where('status', '!=', 'cancelled')->sum('subtotal');
    }

    // Bill so far = sent items (not cancelled) + items still in the cart
    #[Computed]
    public function orderTotal(){
        return $this->sentTotal + $this->cartTotal;
    }

    // The whole order can only be cancelled while the kitchen hasn't started
    // anything, i.e. every sent item is still 'pending' (or already cancelled).
    #[Computed]
    public function canCancelOrder(){
        return $this->sentItems->whereNotIn('status', ['pending', 'cancelled'])->isEmpty();
    }

    // ---------------------------------------------------------------------
    // SEND TO KITCHEN
    // Saves every cart line as a row in order_items, updates the totals on
    // the orders table, then empties the cart.
    // ---------------------------------------------------------------------
    public function sendToKitchen(){
        if (empty($this->cart)) {
            return;
        }

        $order = Order::where('status', 'open')->findOrFail($this->info['order_id']);

        // The cart is a public property, so a clever user could change its
        // values in the browser. That's why we re-read the PRICE and NAME
        // from the database here instead of using the cart's copy.
        // SQL: SELECT * FROM menu_items WHERE id IN (5, 9)
        $menus = MenuItem::whereIn('id', array_keys($this->cart))->get()->keyBy('id');

        // DB::transaction = all queries succeed together, or none are saved.
        // If anything fails half way, the database is rolled back.
        DB::transaction(function () use ($order, $menus) {
            foreach ($this->cart as $menuId => $item) {
                $menu = $menus->get($menuId);
                if (! $menu) {
                    continue; // item was deleted in the meantime
                }
                $qty = max(1, (int) $item['qty']);

                // INSERT INTO order_items (...) VALUES (...)
                $order->orderItems()->create([
                    'menu_item_id' => $menu->id,
                    'item_name'    => $menu->name,
                    'unit_price'   => $menu->price,
                    'quantity'     => $qty,
                    'subtotal'     => $menu->price * $qty,
                    'note'         => trim($item['note'] ?? '') ?: null,
                    'status'       => 'pending', // the kitchen hasn't started it yet
                ]);
            }

            $this->recalculateTotals($order);
        });

        // count it directly (not with $this->cartCount, which would be cached
        // and still show the old number after we empty the cart)
        $count = collect($this->cart)->sum('qty');
        $this->cart = [];

        // The computed sentItems was already calculated in this request,
        // unset() clears it so the newly saved items show up.
        unset($this->sentItems);

        Flux::toast(
            variant: 'success',
            heading: 'Sent to kitchen',
            text: $count.' '.str('item')->plural($count).' sent for table '.$this->info['table_name'],
        );
    }

    // Re-calculate the bill from all items of the order that are NOT cancelled.
    // protected = can only be called from inside this class, not from the page.
    // SQL: SELECT SUM(subtotal) FROM order_items
    //      WHERE order_id = ? AND status != 'cancelled'
    protected function recalculateTotals(Order $order){
        $subtotal = $order->orderItems()->where('status', '!=', 'cancelled')->sum('subtotal');
        $order->update([
            'subtotal'     => $subtotal,
            'total_amount' => $subtotal + $order->tax_amount - $order->discount_amount,
        ]);
    }

    // ---------------------------------------------------------------------
    // CANCELLING
    // Rule: only items with status 'pending' (kitchen hasn't started) can be
    // cancelled. We never DELETE the row — we set status = 'cancelled' and
    // keep the reason, so there is always a record of what happened.
    // ---------------------------------------------------------------------

    // Step 1: the ✕ button next to a sent item opens the "reason" pop-up
    public function confirmCancelItem($orderItemId){
        // make sure the item belongs to THIS order and is still pending
        $item = OrderItem::where('order_id', $this->info['order_id'])
            ->where('status', 'pending')
            ->findOrFail($orderItemId);

        $this->cancellingItemId = $item->id;
        $this->cancelReason = '';
        $this->resetValidation();
        $this->showCancelItemModal = true;
    }

    // Step 2: the waiter typed a reason and clicked "Cancel item"
    public function cancelItem(){
        $this->validate(
            ['cancelReason' => 'required|string|min:3|max:100'],
            ['cancelReason.required' => 'Please give a reason for cancelling'],
        );

        // Check again — the kitchen may have started the item in the meantime,
        // and $cancellingItemId could have been changed in the browser.
        $item = OrderItem::where('order_id', $this->info['order_id'])
            ->where('status', 'pending')
            ->findOrFail($this->cancellingItemId);

        // UPDATE order_items SET status = 'cancelled', ... WHERE id = ?
        $item->update([
            'status'        => 'cancelled',
            'cancel_reason' => $this->cancelReason,
            'cancelled_at'  => now(),
        ]);

        $this->recalculateTotals($item->order);

        $this->showCancelItemModal = false;
        $this->cancellingItemId = null;
        $this->cancelReason = '';
        unset($this->sentItems);

        Flux::toast(heading: 'Item cancelled', text: $item->quantity.'× '.$item->item_name.' was cancelled.');
    }

    // Step 1: the "Cancel order" button opens the "reason" pop-up
    public function confirmCancelOrder(){
        if (! $this->canCancelOrder) {
            Flux::toast(variant: 'danger', heading: 'Cannot cancel', text: 'The kitchen has already started this order.');
            return;
        }

        $this->cancelReason = '';
        $this->resetValidation();
        $this->showCancelOrderModal = true;
    }

    // Step 2: cancel every pending item, the order itself, and free the table
    public function cancelOrder(){
        $this->validate(
            ['cancelReason' => 'required|string|min:3|max:100'],
            ['cancelReason.required' => 'Please give a reason for cancelling'],
        );

        if (! $this->canCancelOrder) {
            Flux::toast(variant: 'danger', heading: 'Cannot cancel', text: 'The kitchen has already started this order.');
            return;
        }

        $order = Order::where('status', 'open')->findOrFail($this->info['order_id']);

        DB::transaction(function () use ($order) {
            // UPDATE order_items SET status = 'cancelled' ... WHERE order_id = ? AND status = 'pending'
            $order->orderItems()->where('status', 'pending')->update([
                'status'        => 'cancelled',
                'cancel_reason' => $this->cancelReason,
                'cancelled_at'  => now(),
            ]);

            $this->recalculateTotals($order);

            // the order's "notes" column keeps the reason
            $order->update(['status' => 'cancelled', 'notes' => 'Cancelled: '.$this->cancelReason]);

            // the table is free again
            $order->cafeTable()->update(['status' => 'available']);
        });

        // forget the order in the session and go back to the tables
        session()->forget('order');
        session()->flash('status', 'Order for table '.$this->info['table_name'].' was cancelled.');

        return $this->redirect(route('select-table'));
    }
};
?>

{{-- wire:init = calls showMenuItems() once the page has loaded, so all items show straight away --}}
<div wire:init="showMenuItems" class="mx-auto max-w-7xl space-y-6">

    {{-- Order info bar --}}
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-linear-to-r from-amber-500 to-orange-500 p-5 text-white shadow-sm">
        <div class="flex items-center gap-4">
            <div class="flex size-12 items-center justify-center rounded-lg bg-white/20">
                <flux:icon.squares-2x2 class="size-6" />
            </div>
            <div>
                <p class="text-sm text-white/80">Table</p>
                <p class="text-2xl font-bold leading-tight">{{ $info['table_name'] }}</p>
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
            <span class="flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1">
                <flux:icon.shopping-bag variant="micro" /> {{ $info['order_type'] == 'takeout' ? 'Takeout' : 'Dine In' }}
            </span>
            <flux:button href="{{ route('select-table') }}" size="sm" variant="ghost" icon="arrow-left" class="text-white! hover:bg-white/20!">
                Change table
            </flux:button>

            {{-- Only shown while the kitchen hasn't started anything --}}
            @if($this->canCancelOrder)
                <flux:button wire:click="confirmCancelOrder" size="sm" variant="ghost" icon="x-circle" class="text-white! hover:bg-white/20!">
                    Cancel order
                </flux:button>
            @endif
        </div>
    </div>

    {{--
        HOW THIS PAGE WORKS (see ORDER-PAGE.md for the full explanation)
        -----------------------------------------------------------------
        1. Tap a menu card       -> wire:click="addItem(id)"     -> added to $cart
        2. Change qty / remove   -> increaseQty / decreaseQty / removeItem
        3. Type a kitchen note   -> wire:model.blur="cart.ID.note"
        4. Send to Kitchen       -> sendToKitchen() saves $cart into order_items

        $this->cartCount, $this->cartTotal, $this->sentItems, $this->orderTotal
        are #[Computed] properties defined in the PHP class above.
    --}}

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Menu --}}
        {{-- x-data "search" = Alpine.js filter that runs in the browser, no server request --}}
        <div x-data="{ search: '' }" class="space-y-4 lg:col-span-2">

            {{-- Search --}}
            <flux:input x-model="search" icon="magnifying-glass" placeholder="Search menu..." clearable />

            {{--
                Category pills. x-data keeps track of the selected pill in the
                browser (Alpine.js) just to highlight it — no server state needed.
            --}}
            <div x-data="{ active: null }" class="flex gap-2 overflow-x-auto pb-1">
                <button
                    type="button"
                    wire:click="showMenuItems()"
                    @click="active = null"
                    :class="active === null ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-stone-700 border-stone-200 hover:border-amber-400 dark:bg-zinc-900 dark:text-zinc-300 dark:border-zinc-700'"
                    class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition"
                >
                    All
                </button>
                @foreach($categories as $cat)
                    <button
                        type="button"
                        wire:key="cat-{{ $cat['id'] }}"
                        wire:click="showMenuItems({{ $cat['id'] }})"
                        @click="active = {{ $cat['id'] }}"
                        :class="active === {{ $cat['id'] }} ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-stone-700 border-stone-200 hover:border-amber-400 dark:bg-zinc-900 dark:text-zinc-300 dark:border-zinc-700'"
                        class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition"
                    >
                        {{ $cat['name'] }}
                    </button>
                @endforeach
            </div>

            {{-- Menu items: tap a card to add one to the order --}}
            <div wire:loading.class="opacity-50" wire:target="showMenuItems" class="grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 xl:grid-cols-4">
                @forelse($menuItems as $menu)
                    @php($qtyInCart = $cart[$menu->id]['qty'] ?? 0)

                    {{--
                        wire:click="addItem(id)" = add one of this item to the cart.
                        data-name + x-show = hide the card when it doesn't match the search box.
                    --}}
                    <button
                        type="button"
                        wire:key="menu-{{ $menu->id }}"
                        wire:click="addItem({{ $menu->id }})"
                        data-name="{{ Str::lower($menu->name) }}"
                        x-show="$el.dataset.name.includes(search.toLowerCase().trim())"
                        @class([
                            'group relative flex flex-col overflow-hidden rounded-xl border bg-white text-left shadow-xs transition dark:bg-zinc-900',
                            'hover:-translate-y-0.5 hover:shadow-md active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500',
                            'border-amber-400 ring-2 ring-amber-400 dark:border-amber-500 dark:ring-amber-500' => $qtyInCart > 0,
                            'border-stone-200 dark:border-zinc-800' => $qtyInCart === 0,
                        ])
                    >
                        {{-- Quantity badge: how many of this item are in the cart --}}
                        @if($qtyInCart > 0)
                            <span class="absolute right-2 top-2 z-10 flex size-7 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-white shadow">
                                {{ $qtyInCart }}
                            </span>
                        @endif

                        {{-- image_url comes from the imageUrl() accessor in the MenuItem model --}}
                        @if($menu->image_url)
                            <img src="{{ $menu->image_url }}" alt="{{ $menu->name }}" loading="lazy" class="aspect-4/3 w-full object-cover">
                        @else
                            <div class="flex aspect-4/3 w-full items-center justify-center bg-amber-50 text-amber-300 dark:bg-zinc-800 dark:text-zinc-600">
                                <flux:icon.photo class="size-10" />
                            </div>
                        @endif

                        <div class="flex flex-1 flex-col justify-between gap-2 p-3">
                            <p class="line-clamp-2 font-semibold leading-snug text-stone-800 dark:text-zinc-100">{{ $menu->name }}</p>
                            <div class="flex items-center justify-between">
                                <p class="font-bold text-amber-600 dark:text-amber-400">₹{{ number_format((float) $menu->price, 0) }}</p>
                                <span class="flex size-7 items-center justify-center rounded-full bg-stone-100 text-stone-500 transition group-hover:bg-amber-500 group-hover:text-white dark:bg-zinc-800 dark:text-zinc-400">
                                    <flux:icon.plus variant="micro" />
                                </span>
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-stone-300 px-6 py-16 text-center dark:border-zinc-700">
                        <flux:icon.book-open class="mx-auto mb-3 size-8 text-stone-400" />
                        <flux:heading>No menu items for the selected category</flux:heading>
                        <flux:text class="mt-1">Choose another category above.</flux:text>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Order summary: quantities are changed here, not on the menu cards --}}
        <aside id="order-summary" class="scroll-mt-6 lg:sticky lg:top-6 lg:self-start">
            <div class="flex max-h-[calc(100vh-3rem)] flex-col rounded-xl border border-stone-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center gap-2 border-b border-stone-200 px-5 py-4 dark:border-zinc-800">
                    <flux:icon.clipboard-document-list class="size-5 text-amber-500" />
                    <flux:heading size="lg">Current Order</flux:heading>
                    @if($this->cartCount > 0)
                        <flux:badge size="sm" color="amber" class="ml-auto">{{ $this->cartCount }} new</flux:badge>
                    @endif
                </div>

                {{-- Order lines --}}
                <div class="flex-1 overflow-y-auto">

                    {{-- Items already saved in order_items (sent earlier) --}}
                    @if($this->sentItems->isNotEmpty())
                        <div class="border-b border-stone-200 bg-stone-50 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-800/40">
                            <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-stone-500 dark:text-zinc-400">
                                <flux:icon.check-circle variant="micro" class="text-emerald-500" /> Sent to kitchen
                            </p>
                            <ul class="space-y-1.5 text-sm">
                                @foreach($this->sentItems as $sent)
                                    @php($isCancelled = $sent->status === 'cancelled')
                       -             <li wire:key="sent-{{ $sent->id }}" class="flex items-start justify-between gap-2">
                                        <span @class(['min-w-0', 'text-stone-400 dark:text-zinc-500' => $isCancelled])>
                                            <span @class(['line-through' => $isCancelled])>
                                                <span class="font-medium tabular-nums">{{ $sent->quantity }}×</span>
                                                {{ $sent->item_name }}
                                            </span>
                                            @if($isCancelled)
                                                <flux:badge size="sm" color="red" class="ml-1">Cancelled</flux:badge>
                                                <span class="block truncate text-xs">{{ $sent->cancel_reason }}</span>
                                            @elseif($sent->status !== 'pending')
                                                <flux:badge size="sm" color="sky" class="ml-1">{{ ucfirst($sent->status) }}</flux:badge>
                                            @endif
                                            @if($sent->note && ! $isCancelled)
                                                <span class="block truncate text-xs italic text-stone-500 dark:text-zinc-400">“{{ $sent->note }}”</span>
                                            @endif
                                        </span>
                                        <span class="flex shrink-0 items-center gap-1">
                                            <span @class(['tabular-nums', 'text-stone-600 dark:text-zinc-300' => ! $isCancelled, 'text-stone-400 line-through dark:text-zinc-500' => $isCancelled])>
                                                ₹{{ number_format((float) $sent->subtotal, 2) }}
                                            </span>
                                            {{-- Only pending items (kitchen hasn't started) can be cancelled --}}
                                            @if($sent->status === 'pending')
                                                <flux:tooltip content="Cancel item">
                                                    <flux:button size="xs" variant="ghost" icon="x-mark" class="text-red-500! hover:bg-red-50! dark:hover:bg-red-500/10!" wire:click="confirmCancelItem({{ $sent->id }})" />
                                                </flux:tooltip>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Cart: new items not sent to the table/database yet --}}
                    <div class="divide-y divide-stone-100 dark:divide-zinc-800">
                    @forelse($cart as $menuId => $item)
                        <div wire:key="cart-{{ $menuId }}" class="space-y-2 px-5 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-stone-800 dark:text-zinc-100">{{ $item['name'] }}</p>
                                    <p class="text-xs text-stone-500 dark:text-zinc-400">₹{{ number_format((float) $item['price'], 0) }} each</p>
                                </div>
                                <p class="font-semibold tabular-nums">₹{{ number_format($item['price'] * $item['qty'], 2) }}</p>
                            </div>

                            <div class="flex items-center justify-between">
                                {{-- Quantity stepper --}}
                                <div class="flex items-center rounded-lg border border-stone-200 dark:border-zinc-700">
                                    <flux:button size="sm" variant="ghost" icon="minus" square wire:click="decreaseQty({{ $menuId }})" />
                                    <span class="w-8 text-center text-sm font-semibold tabular-nums">{{ $item['qty'] }}</span>
                                    <flux:button size="sm" variant="ghost" icon="plus" square wire:click="increaseQty({{ $menuId }})" />
                                </div>

                                <flux:button size="sm" variant="ghost" icon="trash" class="text-red-500! hover:bg-red-50! dark:hover:bg-red-500/10!" wire:click="removeItem({{ $menuId }})" />
                            </div>

                            {{--
                                Kitchen note, e.g. "no sugar".
                                wire:model.blur = update $cart[id]['note'] when the input loses focus
                                (dot notation "cart.5.note" reaches inside the array)
                            --}}
                            <flux:input size="sm" icon="pencil" placeholder="Add note for kitchen..." maxlength="100" wire:model.blur="cart.{{ $menuId }}.note" />
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center">
                            <flux:icon.shopping-bag class="mx-auto mb-2 size-8 text-stone-300 dark:text-zinc-600" />
                            <flux:text>{{ $this->sentItems->isEmpty() ? 'No items added yet.' : 'No new items.' }}</flux:text>
                            <flux:text class="text-xs">Tap a menu item to add it.</flux:text>
                        </div>
                    @endforelse
                    </div>
                </div>

                {{-- Totals --}}
                <div class="space-y-2 border-t border-stone-200 px-5 py-4 text-sm dark:border-zinc-800">
                    @if($this->sentItems->isNotEmpty())
                        <div class="flex justify-between"><flux:text>Already sent</flux:text><span class="tabular-nums">₹{{ number_format($this->sentTotal, 2) }}</span></div>
                    @endif
                    <div class="flex justify-between"><flux:text>New items</flux:text><span class="tabular-nums">₹{{ number_format($this->cartTotal, 2) }}</span></div>
                    <flux:separator class="my-2" />
                    <div class="flex justify-between text-base font-bold"><span>Order total</span><span class="tabular-nums">₹{{ number_format($this->orderTotal, 2) }}</span></div>

                    {{--
                        wire:click="sendToKitchen" = save the cart into order_items.
                        wire:loading.attr="disabled" = disable the button while saving,
                        so a double tap can't send the same items twice.
                    --}}
                    <flux:button
                        variant="primary"
                        icon="paper-airplane"
                        class="mt-3 w-full"
                        wire:click="sendToKitchen"
                        wire:loading.attr="disabled"
                        wire:target="sendToKitchen"
                        :disabled="$this->cartCount === 0"
                    >
                        Send to Kitchen
                    </flux:button>

                    {{--
                        Checkout reads the saved order, not this cart.
                        Hide the link while new items are still only in memory,
                        or while nothing has been sent yet.
                    --}}
                    @if($this->cartCount === 0 && $this->sentTotal > 0)
                        <flux:button href="{{ route('checkout') }}" variant="outline" icon="banknotes" class="w-full">
                            Checkout
                        </flux:button>
                    @else
                        <flux:button variant="outline" icon="banknotes" class="w-full" disabled>
                            Checkout
                        </flux:button>
                        <flux:text class="text-center text-xs">
                            {{ $this->cartCount > 0
                                ? 'Send the new items to the kitchen before checkout.'
                                : 'Send at least one item to the kitchen before checkout.' }}
                        </flux:text>
                    @endif
                </div>
            </div>
        </aside>
    </div>

    {{-- Mobile only: bottom bar that jumps down to the order summary --}}
    @if($this->cartCount > 0)
        <a href="#order-summary" class="fixed inset-x-4 bottom-4 z-20 flex items-center justify-between rounded-xl bg-amber-500 px-5 py-3 font-semibold text-white shadow-lg lg:hidden">
            <span>{{ $this->cartCount }} new {{ Str::plural('item', $this->cartCount) }} · ₹{{ number_format($this->cartTotal, 2) }}</span>
            <span class="flex items-center gap-1">View order <flux:icon.chevron-down variant="micro" /></span>
        </a>
    @endif

    {{-- Cancel ONE item: asks for a reason --}}
    <flux:modal wire:model.self="showCancelItemModal" name="cancel-item" class="w-full md:w-md">
        <form wire:submit="cancelItem" class="space-y-6">
            <div>
                <flux:heading size="lg">Cancel item?</flux:heading>
                <flux:text class="mt-2">The kitchen will see this item as cancelled. It will be removed from the bill.</flux:text>
            </div>

            <flux:input wire:model="cancelReason" label="Reason" placeholder="e.g. Customer changed mind" maxlength="100" autofocus />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Keep item</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Cancel item</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Cancel the WHOLE order: asks for a reason, frees the table --}}
    <flux:modal wire:model.self="showCancelOrderModal" name="cancel-order" class="w-full md:w-md">
        <form wire:submit="cancelOrder" class="space-y-6">
            <div>
                <flux:heading size="lg">Cancel the whole order?</flux:heading>
                <flux:text class="mt-2">
                    All items for table <strong>{{ $info['table_name'] }}</strong> will be cancelled and the table will be available again.
                    Items not yet sent to the kitchen will be discarded.
                </flux:text>
            </div>

            <flux:input wire:model="cancelReason" label="Reason" placeholder="e.g. Guests left" maxlength="100" autofocus />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Keep order</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Cancel order</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
