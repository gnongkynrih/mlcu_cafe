<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body>
        <flux:toast />
        <flux:sidebar sticky collapsible="mobile" class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="#"
                logo="https://fluxui.dev/img/demo/logo.png"
                logo:dark="https://fluxui.dev/img/demo/dark-mode-logo.png"
                name="Acme Inc."
            />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>
        <flux:sidebar.search placeholder="Search..." />
        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="#" current>Home</flux:sidebar.item>
            
            <flux:sidebar.group expandable heading="Admin" class="grid">
                <flux:sidebar.item href="{{route('category-management')}}">Category</flux:sidebar.item>
                <flux:sidebar.item href="{{route('menu-management')}}">Menu</flux:sidebar.item>
                <flux:sidebar.item href="{{route('table-management')}}">Table</flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>
        <flux:sidebar.spacer />
        <flux:sidebar.nav>
            <flux:sidebar.item icon="cog-6-tooth" href="#">Settings</flux:sidebar.item>
            <flux:sidebar.item icon="information-circle" href="#">Help</flux:sidebar.item>
        </flux:sidebar.nav>
        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:sidebar.profile avatar="https://fluxui.dev/img/demo/user.png" name="Olivia Martin" />
            <flux:menu>
                <flux:menu.radio.group>
                    <flux:menu.radio checked>Olivia Martin</flux:menu.radio>
                    <flux:menu.radio>Truly Delta</flux:menu.radio>
                </flux:menu.radio.group>
                <flux:menu.separator />
                {{--
                    Logout must be a POST request, so we wrap the menu item in a
                    form. as="button" type="submit" turns it into a real submit
                    button — no wire:click needed (layouts aren't Livewire
                    components, so wire: directives have nothing to call).
                --}}
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    <flux:menu.item
                        icon="arrow-right-start-on-rectangle"
                        as="button"
                        type="submit"
                        class="w-full cursor-pointer"
                    >Logout</flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>
        {{ $slot }}
       
        @fluxScripts
    </body>
</html>
