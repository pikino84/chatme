<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">{{ $sequence ? 'Editar Secuencia' : 'Nueva Secuencia Drip' }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>
            @endif

            <div class="bg-white shadow rounded-lg p-6">
                <form method="POST" action="{{ $sequence ? route('drip.update', $sequence) : route('drip.store') }}">
                    @csrf
                    @if($sequence)
                        @method('PUT')
                    @endif

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                        <input type="text" name="name" value="{{ old('name', $sequence?->name) }}" required
                               class="w-full border rounded px-3 py-2 @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Evento trigger (opcional)</label>
                        <input type="text" name="trigger_event" value="{{ old('trigger_event', $sequence?->trigger_event) }}"
                               placeholder="ej: contact_created"
                               class="w-full border rounded px-3 py-2">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
                            {{ $sequence ? 'Guardar Cambios' : 'Crear Secuencia' }}
                        </button>
                        <a href="{{ $sequence ? route('drip.show', $sequence) : route('drip.index') }}"
                           class="bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
