<x-guest-layout>
    <x-authentication-card>
        <x-validation-errors class="mb-4" />

        @session('status')
            <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                {{ $value }}
            </div>
        @endsession

        <h2 class="text-2xl font-bold text-gray-900 text-center mb-1">Iniciar Sesión</h2>
        <p class="text-sm text-gray-500 text-center mb-6">Accede a tu cuenta de ChatMe</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-label for="email" value="Correo electrónico" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="tu@email.com" />
            </div>

            <div class="mt-4">
                <x-label for="password" value="Contraseña" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="flex items-center justify-between mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ms-2 text-sm text-gray-600">Recordarme</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="text-sm text-crea-link hover:text-crea-link-hover transition-colors" href="{{ route('password.request') }}">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>

            <div class="mt-6">
                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-crea-primary hover:bg-crea-secondary border border-transparent rounded-lg font-semibold text-sm text-white tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-crea-secondary disabled:opacity-50 transition ease-in-out duration-150">
                    Iniciar Sesión
                </button>
            </div>

            <div class="flex items-center justify-center mt-4">
                <a class="text-sm text-crea-link hover:text-crea-link-hover transition-colors" href="{{ route('register') }}">
                    Crear cuenta
                </a>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
