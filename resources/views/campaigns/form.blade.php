<x-app-layout>
    <x-slot name="header">{{ $campaign ? 'Editar Campaña' : 'Nueva Campaña' }}</x-slot>

    @if(session('error'))
        <div class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="max-w-2xl">
        <form method="POST"
              action="{{ $campaign ? route('campaigns.update', $campaign) : route('campaigns.store') }}"
              class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-4">
            @csrf
            @if($campaign) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                <input type="text" name="name" value="{{ old('name', $campaign?->name) }}" required
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Canal *</label>
                <select name="channel_id" required
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    <option value="">Seleccionar canal</option>
                    @foreach($channels as $ch)
                        <option value="{{ $ch->id }}" {{ old('channel_id', $campaign?->channel_id) == $ch->id ? 'selected' : '' }}>
                            {{ $ch->name }} ({{ $ch->type }})
                        </option>
                    @endforeach
                </select>
                @error('channel_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mensaje *</label>
                <textarea name="message_template" rows="5" required
                          class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">{{ old('message_template', $campaign?->message_template) }}</textarea>
                @error('message_template') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    {{ $campaign ? 'Actualizar' : 'Crear' }}
                </button>
                <a href="{{ $campaign ? route('campaigns.show', $campaign) : route('campaigns.index') }}"
                   class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg">Cancelar</a>
            </div>
        </form>
    </div>
</x-app-layout>
