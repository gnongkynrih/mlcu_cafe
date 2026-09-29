<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-800 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <flux:toast />

        <flux:sidebar sticky collapsible="mobile" class="bg-white dark:bg-zinc-900 border-r border-stone-200 dark:border-zinc-800">
            <flux:sidebar.header>
                <flux:sidebar.brand href="{{ route('dashboard') }}" name="MLCU Cafe">
                    <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-amber-500 text-white">
                        <flux:icon.cake class="size-5" />
                    </x-slot>
                </flux:sidebar.brand>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            {{--
                :current="request()->routeIs('...')" highlights the menu item
                of the page we are currently on.
            --}}
            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" href="{{ route('dashboard') }}" :current="request()->routeIs('dashboard')">
                    Dashboard
                </flux:sidebar.item>
                <flux:sidebar.item icon="receipt-percent" href="{{ route('select-table') }}" :current="request()->routeIs('select-table', 'take-order')">
                    Take Order
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:sidebar.nav>
                <flux:sidebar.group heading="Admin" class="grid">
                    <flux:sidebar.item icon="tag" href="{{ route('category-management') }}" :current="request()->routeIs('category-management')">
                        Categories
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="book-open" href="{{ route('menu-management') }}" :current="request()->routeIs('menu-management')">
                        Menu Items
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="squares-2x2" href="{{ route('table-management') }}" :current="request()->routeIs('table-management')">
                        Tables
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" href="{{ route('profile.edit') }}" :current="request()->routeIs('profile.edit', 'appearance.edit', 'security.edit')">
                    Settings
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <div class="max-lg:hidden">
                <x-desktop-user-menu />
            </div>
        </flux:sidebar>

        {{-- Mobile top bar: shows the menu toggle button on small screens --}}
        <flux:header class="lg:hidden bg-white dark:bg-zinc-900 border-b border-stone-200 dark:border-zinc-800">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <span class="ml-2 font-semibold">MLCU Cafe</span>
            <flux:spacer />
            <x-desktop-user-menu />
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
