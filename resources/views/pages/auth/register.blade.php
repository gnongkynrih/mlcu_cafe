<x-layouts::app :title="__('Register User')">
    <div class="mx-auto max-w-2xl space-y-6">

        {{-- Header --}}
        <div>
            <flux:heading size="xl" level="1">{{ __('Register User') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Create a login for a new staff member.') }}</flux:text>
        </div>

        {{-- Success message after an account is created --}}
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
        @endif
        <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-xs sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
            <form method="POST" action="{{ route('register.store') }}" class="space-y-6">
                @csrf

                <div class="grid gap-5 sm:grid-cols-2">
                    <!-- Name -->
                    <flux:input
                        name="name"
                        :label="__('Name')"
                        :value="old('name')"
                        type="text"
                        required
                        autofocus
                        autocomplete="off"
                        :placeholder="__('Full name')"
                        icon="user"
                    />

                    <!-- Email Address -->
                    <flux:input
                        name="email"
                        :label="__('Email address')"
                        :value="old('email')"
                        type="email"
                        required
                        autocomplete="off"
                        placeholder="email@example.com"
                        icon="envelope"
                    />

                    <!-- Password -->
                    <flux:input
                        name="password"
                        :label="__('Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('Password')"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                        icon="lock-closed"
                        viewable
                    />

                    <!-- Confirm Password -->
                    <flux:input
                        name="password_confirmation"
                        :label="__('Confirm password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('Confirm password')"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                        icon="lock-closed"
                        viewable
                    />
                    <flux:select
                        name="role"
                        :label="__('Role')"
                        
                        :placeholder="__('Select a role')"
                        icon="user"
                    >
                        @foreach($roles as $role)
                            <flux:select.option value="{{ $role->name }}">{{ $role->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:separator />

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <flux:text class="text-sm">{{ __('Share the password with the staff member so they can log in.') }}</flux:text>
                    <flux:button type="submit" variant="primary" icon="user-plus" data-test="register-user-button">
                        {{ __('Create account') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
