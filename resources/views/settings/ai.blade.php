<x-app-layout>
    <div class="max-w-2xl mx-auto py-8 px-4">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">Configuración IA</h2>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        {{-- Status indicators --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Función IA del Plan</span>
                <span class="{{ $featureEnabled ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                    {{ $featureEnabled ? 'Habilitado' : 'No disponible en el plan actual' }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Clave API de OpenAI</span>
                <span class="{{ $apiConfigured ? 'text-green-600 dark:text-green-400' : 'text-yellow-500 dark:text-yellow-400' }}">
                    {{ $apiConfigured ? 'Configurada' : 'No configurada (contactar admin)' }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Búsqueda Vectorial (pgvector)</span>
                <span class="{{ $vectorAvailable ? 'text-green-600 dark:text-green-400' : 'text-yellow-500 dark:text-yellow-400' }}">
                    {{ $vectorAvailable ? 'Disponible' : 'No disponible (respaldo por palabras clave activo)' }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Consultas IA Este Mes</span>
                <span class="text-gray-700 dark:text-gray-300">{{ $usage }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.ai.update') }}" class="space-y-5">
            @csrf

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-4">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="ai_enabled" value="1"
                           @checked(old('ai_enabled', $org->settings['ai_enabled'] ?? false))
                           {{ $featureEnabled ? '' : 'disabled' }}
                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Habilitar respuestas con IA</span>
                </label>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Modelo</label>
                    <select name="ai_model"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        @foreach(['gpt-4o-mini' => 'GPT-4o Mini (fast, affordable)', 'gpt-4o' => 'GPT-4o (balanced)', 'gpt-4-turbo' => 'GPT-4 Turbo (powerful)'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('ai_model', $org->settings['ai_model'] ?? 'gpt-4o-mini') === $val)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Temperatura <span class="text-xs text-gray-400">(0 = preciso, 1 = creativo)</span>
                    </label>
                    <input type="number" name="ai_temperature" step="0.1" min="0" max="1"
                           value="{{ old('ai_temperature', $org->settings['ai_temperature'] ?? 0.3) }}"
                           class="w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                </div>
            </div>

            @can('settings.update')
                <button type="submit"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    Guardar Configuración IA
                </button>
            @endcan
        </form>

        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('settings.show') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Volver a Configuración
            </a>
        </div>
    </div>
</x-app-layout>
