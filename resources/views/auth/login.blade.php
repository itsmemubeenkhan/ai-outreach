<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (config('auth.quick_login.enabled') && config('auth.quick_login.email') && config('auth.quick_login.password'))
        <div class="mb-5 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-sm font-semibold text-indigo-950">Quick admin access</p>
            <p class="mt-1 text-xs text-indigo-700">Copy the login credentials and fill the form automatically.</p>
            <button
                type="button"
                id="copy-admin-login"
                class="mt-3 w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                Copy Login Credentials
            </button>
            <p id="copy-login-status" class="mt-2 hidden text-xs font-medium text-emerald-700" role="status"></p>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    @if (config('auth.quick_login.enabled') && config('auth.quick_login.email') && config('auth.quick_login.password'))
        <script>
            document.getElementById('copy-admin-login').addEventListener('click', async () => {
                const email = @js(config('auth.quick_login.email'));
                const password = @js(config('auth.quick_login.password'));
                const status = document.getElementById('copy-login-status');

                document.getElementById('email').value = email;
                document.getElementById('password').value = password;
                document.getElementById('remember_me').checked = true;
                document.getElementById('email').dispatchEvent(new Event('input', { bubbles: true }));
                document.getElementById('password').dispatchEvent(new Event('input', { bubbles: true }));

                try {
                    await navigator.clipboard.writeText(`Email: ${email}\nPassword: ${password}`);
                    status.textContent = 'Credentials copied and filled.';
                } catch (error) {
                    status.textContent = 'Credentials filled. Clipboard access was unavailable.';
                }

                status.classList.remove('hidden');
                document.querySelector('button[type="submit"]').focus();
            });
        </script>
    @endif
</x-guest-layout>
