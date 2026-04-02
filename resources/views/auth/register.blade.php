<x-guest-layout>
    <x-authentication-card>
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <x-validation-errors class="mb-4" />

        <h2 class="text-2xl font-bold text-gray-900 text-center mb-1">Solicitar Acceso</h2>
        <p class="text-sm text-gray-500 text-center mb-6">Déjanos tus datos y nos pondremos en contacto</p>

        <form method="POST" action="{{ route('access.request') }}">
            @csrf

            <div>
                <x-label for="name" value="Nombre" />
                <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
            </div>

            <div class="mt-4">
                <x-label for="email" value="Correo electrónico" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required placeholder="tu@email.com" />
            </div>

            <div class="mt-4">
                <x-label for="phone" value="Teléfono" />
                <x-input id="phone" class="block mt-1 w-full" type="tel" name="phone" :value="old('phone')" placeholder="+52 998 123 4567" />
            </div>

            <div class="mt-4">
                <x-label for="company" value="Empresa" />
                <x-input id="company" class="block mt-1 w-full" type="text" name="company" :value="old('company')" />
            </div>

            <div class="mt-6">
                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-crea-primary hover:bg-crea-secondary border border-transparent rounded-lg font-semibold text-sm text-white tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-crea-secondary disabled:opacity-50 transition ease-in-out duration-150">
                    Solicitar Acceso
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
