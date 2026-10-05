<?php

use Livewire\Component;
use App\Models\Category;
use App\Models\MenuItem;
new class extends Component
{
    public $info;
    public $categories = [];
    public $menuItems = [];
    public function mount(){
        //check if the session order is there or not
        if(!session('order')){
            return $this->redirect(route('select-table'));
        }
        $this->info = session()->get('order');

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
        </div>
    </div>

    {{--
        ORDER CART (to be built in the PHP class above)
        ------------------------------------------------
        The layout below reads a public $cart property shaped like this,
        keyed by the menu item id:

            public $cart = [
                5 => ['name' => 'Cappuccino', 'price' => 120, 'qty' => 2, 'note' => 'no sugar'],
                9 => ['name' => 'Brownie',    'price' => 80,  'qty' => 1, 'note' => ''],
            ];

        Until you add it, "$cart ?? []" means "use an empty array", so the page
        still works and simply shows "No items added yet".

        Methods to write (the comments marked TODO show where to call them):
            addItem($menuId)       -> qty + 1 (adds the item if it isn't in the cart)
            increaseQty($menuId)   -> qty + 1
            decreaseQty($menuId)   -> qty - 1 (remove the item when qty reaches 0)
            removeItem($menuId)    -> unset($this->cart[$menuId])
    --}}
    @php
    
        $cartItems = $cart ?? [];
        $itemCount = collect($cartItems)->sum('qty');
        $subtotal = collect($cartItems)->sum(fn ($item) => $item['price'] * $item['qty']);
    @endphp

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
                    @php($qtyInCart = $cartItems[$menu->id]['qty'] ?? 0)

                    {{--
                        TODO: add  wire:click="addItem({{ $menu->id }})"  to this button.
                        data-name + x-show = hide the card when it doesn't match the search box.
                    --}}
                    <button
                        type="button"
                        wire:key="menu-{{ $menu->id }}"
                        data-name="{{ Str::lower($menu->name) }}"
                        x-show="$el.dataset.name.includes(search.toLowerCase().trim())"
                        @class([
                            'group relative flex flex-col overflow-hidden rounded-xl border bg-white text-left shadow-xs transition dark:bg-zinc-900',
                            'hover:-translate-y-0.5 hover:shadow-md active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500',
                            'border-amber-400 ring-2 ring-amber-400 dark:border-amber-500 dark:ring-amber-500' => $qtyInCart > 0,
                            'border-stone-200 dark:border-zinc-800' => $qtyInCart === 0,
                        ])
                    >
                        {{-- Quantity badge: shows how many of this item are already in the order --}}
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
                                <p class="font-bold text-amber-600 dark:text-amber-400">₹{{ number_format((float) $menu->price, 2) }}</p>
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
                    @if($itemCount > 0)
                        <flux:badge size="sm" color="amber" class="ml-auto">{{ $itemCount }} {{ Str::plural('item', $itemCount) }}</flux:badge>
                    @endif
                </div>

                {{-- Order lines --}}
                <div class="flex-1 divide-y divide-stone-100 overflow-y-auto dark:divide-zinc-800">
                    @forelse($cartItems as $menuId => $item)
                        <div wire:key="cart-{{ $menuId }}" class="space-y-2 px-5 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-stone-800 dark:text-zinc-100">{{ $item['name'] }}</p>
                                    <p class="text-xs text-stone-500 dark:text-zinc-400">₹{{ number_format((float) $item['price'], 2) }} each</p>
                                </div>
                                <p class="font-semibold tabular-nums">₹{{ number_format($item['price'] * $item['qty'], 2) }}</p>
                            </div>

                            <div class="flex items-center justify-between">
                                {{-- Quantity stepper --}}
                                <div class="flex items-center rounded-lg border border-stone-200 dark:border-zinc-700">
                                    {{-- TODO: wire:click="decreaseQty({{ $menuId }})" --}}
                                    <flux:button size="sm" variant="ghost" icon="minus" square />
                                    <span class="w-8 text-center text-sm font-semibold tabular-nums">{{ $item['qty'] }}</span>
                                    {{-- TODO: wire:click="increaseQty({{ $menuId }})" --}}
                                    <flux:button size="sm" variant="ghost" icon="plus" square />
                                </div>

                                {{-- TODO: wire:click="removeItem({{ $menuId }})" --}}
                                <flux:button size="sm" variant="ghost" icon="trash" class="text-red-500! hover:bg-red-50! dark:hover:bg-red-500/10!" />
                            </div>

                            {{-- Kitchen note, e.g. "no sugar". TODO: wire:model.blur="cart.{{ $menuId }}.note" --}}
                            <flux:input size="sm" icon="pencil" placeholder="Add note for kitchen..." value="{{ $item['note'] ?? '' }}" />
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center">
                            <flux:icon.shopping-bag class="mx-auto mb-2 size-8 text-stone-300 dark:text-zinc-600" />
                            <flux:text>No items added yet.</flux:text>
                            <flux:text class="text-xs">Tap a menu item to add it.</flux:text>
                        </div>
                    @endforelse
                </div>

                {{-- Totals --}}
                <div class="space-y-2 border-t border-stone-200 px-5 py-4 text-sm dark:border-zinc-800">
                    <div class="flex justify-between"><flux:text>Subtotal</flux:text><span class="tabular-nums">₹{{ number_format($subtotal, 2) }}</span></div>
                    <flux:separator class="my-2" />
                    <div class="flex justify-between text-base font-bold"><span>Total</span><span class="tabular-nums">₹{{ number_format($subtotal, 2) }}</span></div>
                    {{-- TODO: wire:click="sendToKitchen" (save the order items to the database) --}}
                    <flux:button variant="primary" icon="paper-airplane" class="mt-3 w-full" :disabled="$itemCount === 0">Send to Kitchen</flux:button>
                </div>
            </div>
        </aside>
    </div>

    {{-- Mobile only: bottom bar that jumps down to the order summary --}}
    @if($itemCount > 0)
        <a href="#order-summary" class="fixed inset-x-4 bottom-4 z-20 flex items-center justify-between rounded-xl bg-amber-500 px-5 py-3 font-semibold text-white shadow-lg lg:hidden">
            <span>{{ $itemCount }} {{ Str::plural('item', $itemCount) }} · ₹{{ number_format($subtotal, 2) }}</span>
            <span class="flex items-center gap-1">View order <flux:icon.chevron-down variant="micro" /></span>
        </a>
    @endif
</div>
