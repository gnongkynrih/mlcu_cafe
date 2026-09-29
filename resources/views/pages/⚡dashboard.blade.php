<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">Welcome back, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">What would you like to do today?</flux:text>
    </div>

    {{-- Quick links --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['route' => 'select-table', 'icon' => 'receipt-percent', 'title' => 'Take Order', 'text' => 'Pick a table and start an order.'],
            ['route' => 'category-management', 'icon' => 'tag', 'title' => 'Categories', 'text' => 'Group your menu into categories.'],
            ['route' => 'menu-management', 'icon' => 'book-open', 'title' => 'Menu Items', 'text' => 'Add dishes, drinks and prices.'],
            ['route' => 'table-management', 'icon' => 'squares-2x2', 'title' => 'Tables', 'text' => 'Set up the tables in your cafe.'],
        ] as $link)
            <a
                href="{{ route($link['route']) }}"
                class="group rounded-xl border border-stone-200 bg-white p-5 shadow-xs transition hover:-translate-y-0.5 hover:border-amber-400 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-amber-500/60"
            >
                <div class="mb-4 flex size-11 items-center justify-center rounded-lg bg-amber-50 text-amber-600 transition group-hover:bg-amber-500 group-hover:text-white dark:bg-amber-500/10 dark:text-amber-400">
                    <flux:icon :icon="$link['icon']" class="size-6" />
                </div>
                <flux:heading size="lg">{{ $link['title'] }}</flux:heading>
                <flux:text class="mt-1">{{ $link['text'] }}</flux:text>
            </a>
        @endforeach
    </div>
</div>
