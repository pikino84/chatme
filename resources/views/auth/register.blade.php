<x-guest-layout>
    <x-authentication-card>
        <x-validation-errors class="mb-4" />

        <h2 class="text-2xl font-bold text-gray-900 text-center mb-1">Crear Cuenta</h2>
        <p class="text-sm text-gray-500 text-center mb-6">Regístrate para comenzar</p>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div>
                <x-label for="name" value="Nombre" />
                <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <div class="mt-4">
                <x-label for="email" value="Correo electrónico" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="tu@email.com" />
            </div>

            <div class="mt-4">
                <x-label for="password" value="Contraseña" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-label for="password_confirmation" value="Confirmar Contraseña" />
                <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div class="mt-4">
                    <x-label for="terms">
                        <div class="flex items-center">
                            <x-checkbox name="terms" id="terms" required />
                            <div class="ms-2">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="text-sm text-crea-link hover:text-crea-link-hover transition-colors">'.__('Terms of Service').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="text-sm text-crea-link hover:text-crea-link-hover transition-colors">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-label>
                </div>
            @endif

            <div class="mt-6">
                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-crea-primary hover:bg-crea-secondary border border-transparent rounded-lg font-semibold text-sm text-white tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-crea-secondary disabled:opacity-50 transition ease-in-out duration-150">
                    Registrarse
                </button>
            </div>

            <div class="flex items-center justify-center mt-4">
                <a class="text-sm text-crea-link hover:text-crea-link-hover transition-colors" href="{{ route('login') }}">
                    ¿Ya tienes cuenta? Inicia sesión
                </a>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
