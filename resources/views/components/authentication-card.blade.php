<div class="min-h-screen flex">
    {{-- Left side - Branding (matches CREA ESPACIOS guest layout) --}}
    <div class="hidden lg:flex lg:w-1/2 bg-crea-primary relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-crea-primary via-crea-primary to-crea-secondary opacity-90"></div>
        <div class="relative z-10 flex flex-col justify-center items-center w-full p-12 text-white">
            <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center mb-8 backdrop-blur-sm">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-center mb-4">ChatMe</h1>
            <p class="text-xl text-center text-white/80 max-w-md">
                Plataforma de comunicación<br>omnicanal para tu negocio
            </p>
            <div class="mt-12 flex items-center space-x-8 text-white/60">
                <div class="text-center">
                    <div class="text-3xl font-bold text-white">WhatsApp</div>
                    <div class="text-sm">Integrado</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-white">CRM</div>
                    <div class="text-sm">Incluido</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-white">AI</div>
                    <div class="text-sm">Asistente</div>
                </div>
            </div>
        </div>
        {{-- Decorative elements --}}
        <div class="absolute bottom-0 left-0 w-full h-32 bg-gradient-to-t from-black/20 to-transparent"></div>
        <div class="absolute -bottom-16 -left-16 w-64 h-64 bg-crea-secondary/20 rounded-full blur-3xl"></div>
        <div class="absolute -top-16 -right-16 w-64 h-64 bg-crea-accent/20 rounded-full blur-3xl"></div>
    </div>

    {{-- Right side - Form --}}
    <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-6 sm:p-12" style="background-color: #f9fafb;">
        {{-- Mobile logo --}}
        <div class="lg:hidden mb-8">
            <div class="flex items-center gap-3 bg-crea-primary px-5 py-3 rounded-xl">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <span class="text-white text-lg font-bold">ChatMe</span>
            </div>
        </div>

        <div class="w-full sm:max-w-md">
            <div class="bg-white shadow-xl rounded-2xl px-8 py-10">
                {{ $slot }}
            </div>
            <p class="text-center text-gray-500 text-sm mt-6">
                &copy; {{ date('Y') }} ChatMe. Todos los derechos reservados.
            </p>
        </div>
    </div>
</div>
