<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'Welcome'])
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-800 antialiased dark:bg-zinc-950 dark:text-zinc-100">

        {{-- Top navigation --}}
        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <a href="{{ route('welcome') }}" class="flex items-center gap-2 text-lg font-semibold">
                <span class="flex size-9 items-center justify-center rounded-lg bg-amber-500 text-white">
                    <flux:icon.cake class="size-5" />
                </span>
                MLCU Cafe
            </a>

            {{-- @auth / @guest = show different buttons if the user is logged in or not --}}
            <nav class="flex items-center gap-2">
                @auth
                    <flux:button href="{{ route('dashboard') }}" variant="primary" icon:trailing="arrow-right">Dashboard</flux:button>
                @else
                    <flux:button href="{{ route('login') }}" variant="primary">Log in</flux:button>
                @endauth
            </nav>
        </header>

        <main>
            {{-- Hero --}}
            <section class="mx-auto grid max-w-6xl items-center gap-12 px-6 py-12 lg:grid-cols-2 lg:py-20">
                <div class="space-y-6">
                    <flux:badge color="amber" icon="sparkles">Cafe management made simple</flux:badge>

                    <h1 class="text-4xl font-bold leading-tight tracking-tight sm:text-5xl">
                        Welcome to <span class="text-amber-500">MLCU Cafe</span>
                    </h1>

                    <p class="max-w-lg text-lg text-stone-600 dark:text-zinc-400">
                        Seat your guests, take their orders and manage your menu — all from one fast, friendly screen.
                    </p>

                    <div class="flex flex-wrap gap-3">
                        @auth
                            <flux:button href="{{ route('select-table') }}" variant="primary" icon="receipt-percent">Take an Order</flux:button>
                            <flux:button href="{{ route('dashboard') }}" variant="outline">Go to Dashboard</flux:button>
                        @else
                            <flux:button href="{{ route('login') }}" variant="primary" icon:trailing="arrow-right">Log in to get started</flux:button>
                        @endauth
                    </div>
                </div>

                {{-- Preview illustration (plain HTML, no images needed) --}}
                <div class="relative">
                    <div class="absolute -inset-6 -z-10 rounded-[2rem] bg-linear-to-br from-amber-200 via-orange-100 to-rose-200 opacity-70 blur-2xl dark:from-amber-500/20 dark:via-orange-500/10 dark:to-rose-500/20"></div>

                    <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center justify-between">
                            <p class="font-semibold">Tables</p>
                            <div class="flex gap-3 text-xs text-stone-500 dark:text-zinc-400">
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Available</span>
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-rose-500"></span> Occupied</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            @foreach ([['T1', true], ['T2', false], ['T3', true], ['T4', true], ['T5', false], ['T6', true]] as [$tableName, $isAvailable])
                                <div @class([
                                    'rounded-xl border-2 p-3',
                                    'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10' => $isAvailable,
                                    'border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' => ! $isAvailable,
                                ])>
                                    <p class="font-bold">{{ $tableName }}</p>
                                    <p @class([
                                        'text-xs',
                                        'text-emerald-700 dark:text-emerald-400' => $isAvailable,
                                        'text-rose-700 dark:text-rose-400' => ! $isAvailable,
                                    ])>{{ $isAvailable ? 'Available' : 'Occupied' }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 flex items-center justify-between rounded-xl bg-amber-500 px-4 py-3 text-white">
                            <span class="text-sm font-medium">T2 · 3 items</span>
                            <span class="font-bold">₹360.00</span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Features --}}
            <section class="mx-auto max-w-6xl px-6 pb-20">
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        ['icon' => 'squares-2x2', 'title' => 'Table management', 'text' => 'See which tables are free or occupied and seat guests in one tap.'],
                        ['icon' => 'book-open', 'title' => 'Menu with photos', 'text' => 'Organize items into categories, set prices and add pictures.'],
                        ['icon' => 'receipt-percent', 'title' => 'Quick ordering', 'text' => 'A touch-friendly order screen built for busy waiters.'],
                    ] as $feature)
                        <div class="rounded-xl border border-stone-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="mb-4 flex size-11 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                <flux:icon :icon="$feature['icon']" class="size-6" />
                            </div>
                            <flux:heading size="lg">{{ $feature['title'] }}</flux:heading>
                            <flux:text class="mt-1">{{ $feature['text'] }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </section>
        </main>

        <footer class="border-t border-stone-200 py-6 text-center text-sm text-stone-500 dark:border-zinc-800 dark:text-zinc-500">
            &copy; {{ date('Y') }} MLCU Cafe
        </footer>

        @fluxScripts
    </body>
</html>
