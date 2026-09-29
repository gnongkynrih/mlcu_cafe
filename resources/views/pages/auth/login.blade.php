<x-layouts::auth :title="__('Log in')">
    <div class="grid min-h-screen bg-stone-50 text-stone-800 lg:grid-cols-2 dark:bg-zinc-950 dark:text-zinc-100">

        {{-- Left: brand panel (hidden on small screens) --}}
        <div class="relative hidden overflow-hidden bg-linear-to-br from-amber-500 via-orange-500 to-rose-500 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            {{-- Decorative circles --}}
            <div class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-16 size-80 rounded-full bg-white/10"></div>

            <a href="{{ route('welcome') }}" class="relative flex items-center gap-3 text-lg font-semibold">
                <span class="flex size-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur">
                    <flux:icon.cake class="size-6" />
                </span>
                MLCU Cafe
            </a>

            <div class="relative max-w-md space-y-6">
                <h2 class="text-4xl font-bold leading-tight">Take orders faster.<br>Serve customers better.</h2>
                <p class="text-lg text-white/85">Manage tables, menus and orders for your cafe — all in one simple place.</p>

                <ul class="space-y-3 text-white/90">
                    <li class="flex items-center gap-3"><flux:icon.check-circle variant="mini" /> Live table status at a glance</li>
                    <li class="flex items-center gap-3"><flux:icon.check-circle variant="mini" /> Menu with photos and categories</li>
                    <li class="flex items-center gap-3"><flux:icon.check-circle variant="mini" /> Quick, touch-friendly ordering</li>
                </ul>
            </div>

            <p class="relative text-sm text-white/70">&copy; {{ date('Y') }} MLCU Cafe</p>
        </div>

        {{-- Right: login form --}}
        <div class="flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-sm space-y-8">

                {{-- Brand for small screens --}}
                <a href="{{ route('welcome') }}" class="flex items-center justify-center gap-2 font-semibold lg:hidden">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-amber-500 text-white">
                        <flux:icon.cake class="size-5" />
                    </span>
                    MLCU Cafe
                </a>

                <div class="space-y-1">
                    <flux:heading size="xl" level="1">{{ __('Welcome back') }}</flux:heading>
                    <flux:text>{{ __('Enter your email and password to log in.') }}</flux:text>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="rounded-lg bg-emerald-50 px-4 py-3 dark:bg-emerald-500/10" :status="session('status')" />

                <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
                    @csrf

                    <!-- Email Address -->
                    <flux:input
                        name="email"
                        :label="__('Email address')"
                        :value="old('email')"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="email@example.com"
                        icon="envelope"
                    />

                    <!-- Password -->
                    <div class="relative">
                        <flux:input
                            name="password"
                            :label="__('Password')"
                            type="password"
                            required
                            autocomplete="current-password"
                            :placeholder="__('Password')"
                            icon="lock-closed"
                            viewable
                        />

                        @if (Route::has('password.request'))
                            <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                                {{ __('Forgot password?') }}
                            </flux:link>
                        @endif
                    </div>

                    <!-- Remember Me -->
                    <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                    <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                        {{ __('Log in') }}
                    </flux:button>
                </form>

                {{-- There is no public sign up: accounts are created by logged-in staff --}}
                <p class="text-center text-sm text-stone-600 dark:text-zinc-400">
                    {{ __('Need an account? Ask your manager to create one for you.') }}
                </p>
            </div>
        </div>
    </div>
</x-layouts::auth>
