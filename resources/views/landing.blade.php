<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ChatMe') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="font-sans antialiased bg-white dark:bg-gray-900">
    <header class="px-6 py-4 flex items-center justify-between max-w-7xl mx-auto">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ config('app.name', 'ChatMe') }}</h1>
        <nav class="flex gap-4">
            <a href="{{ 'http://app.' . config('app.base_domain') . '/login' }}"
               class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                Iniciar Sesion
            </a>
            <a href="{{ 'http://app.' . config('app.base_domain') . '/register' }}"
               class="text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-md">
                Registrarse
            </a>
        </nav>
    </header>

    <main class="flex flex-col items-center justify-center min-h-[70vh] px-6 text-center">
        <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            Plataforma de Mensajeria Omnicanal
        </h2>
        <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mb-8">
            Gestiona todas tus conversaciones de WhatsApp, webchat y mas desde un solo lugar.
            CRM integrado, automatizaciones y reportes en tiempo real.
        </p>
        <a href="{{ 'http://app.' . config('app.base_domain') . '/register' }}"
           class="text-base font-semibold text-white bg-indigo-600 hover:bg-indigo-700 px-8 py-3 rounded-lg">
            Comenzar Gratis
        </a>
    </main>
</body>
</html>
