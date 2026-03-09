<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">{{ $rule ? 'Editar Regla' : 'Nueva Regla de Automatización' }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg p-6">
                <form method="POST" action="{{ $rule ? route('automations.update', $rule) : route('automations.store') }}">
                    @csrf
                    @if($rule)
                        @method('PUT')
                    @endif

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                        <input type="text" name="name" value="{{ old('name', $rule?->name) }}" required
                               class="w-full border rounded px-3 py-2 @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    @if(!$rule)
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                            <select name="type" id="automation-type" required
                                    class="w-full border rounded px-3 py-2" onchange="toggleConfigFields()">
                                <option value="">Seleccionar...</option>
                                <option value="auto_assign" {{ old('type') === 'auto_assign' ? 'selected' : '' }}>Auto-asignación</option>
                                <option value="auto_response" {{ old('type') === 'auto_response' ? 'selected' : '' }}>Auto-respuesta</option>
                                <option value="stale_alert" {{ old('type') === 'stale_alert' ? 'selected' : '' }}>Alerta por inactividad</option>
                            </select>
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Canal (opcional, aplica a todos si vacío)</label>
                        <select name="channel_id" class="w-full border rounded px-3 py-2">
                            <option value="">Todos los canales</option>
                            @foreach($channels as $channel)
                                <option value="{{ $channel->id }}" {{ old('channel_id', $rule?->channel_id) == $channel->id ? 'selected' : '' }}>
                                    {{ $channel->name }} ({{ $channel->type }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $rule?->is_active ?? true) ? 'checked' : '' }}
                                   class="mr-2">
                            <span class="text-sm text-gray-700">Activa</span>
                        </label>
                    </div>

                    {{-- Auto-assign config --}}
                    <div id="config-auto_assign" class="mb-4 {{ ($rule?->type ?? old('type')) === 'auto_assign' ? '' : 'hidden' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Método de asignación</label>
                        <select name="config[method]" class="w-full border rounded px-3 py-2">
                            <option value="round_robin" {{ old('config.method', $rule?->getConfigValue('method')) === 'round_robin' ? 'selected' : '' }}>Round Robin</option>
                            <option value="least_busy" {{ old('config.method', $rule?->getConfigValue('method')) === 'least_busy' ? 'selected' : '' }}>Menos ocupado</option>
                        </select>
                    </div>

                    {{-- Auto-response config --}}
                    <div id="config-auto_response" class="mb-4 {{ ($rule?->type ?? old('type')) === 'auto_response' ? '' : 'hidden' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje automático</label>
                        <textarea name="config[message]" rows="3" class="w-full border rounded px-3 py-2"
                        >{{ old('config.message', $rule?->getConfigValue('message')) }}</textarea>
                    </div>

                    {{-- Stale alert config --}}
                    <div id="config-stale_alert" class="mb-4 {{ ($rule?->type ?? old('type')) === 'stale_alert' ? '' : 'hidden' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Umbral de inactividad (minutos)</label>
                        <input type="number" name="config[threshold_minutes]" min="5"
                               value="{{ old('config.threshold_minutes', $rule?->getConfigValue('threshold_minutes', 30)) }}"
                               class="w-full border rounded px-3 py-2">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
                            {{ $rule ? 'Guardar Cambios' : 'Crear Regla' }}
                        </button>
                        <a href="{{ route('automations.index') }}"
                           class="bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleConfigFields() {
            const type = document.getElementById('automation-type')?.value || '';
            ['auto_assign', 'auto_response', 'stale_alert'].forEach(t => {
                const el = document.getElementById('config-' + t);
                if (el) el.classList.toggle('hidden', t !== type);
            });
        }
    </script>
</x-app-layout>
