<x-app-layout>
    <div class="max-w-2xl mx-auto py-8 px-4">
        <div class="mb-6">
            <a href="{{ route('brands.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">&larr; Marcas</a>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mt-2">
                {{ $brand ? 'Editar Marca' : 'Nueva Marca' }}
            </h2>
        </div>

        @if($errors->any())
            <div class="mb-4 px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ $brand ? route('brands.update', $brand) : route('brands.store') }}" method="POST"
              class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 space-y-5">
            @csrf
            @if($brand)
                @method('PUT')
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre de la Marca *</label>
                <input type="text" name="name" value="{{ old('name', $brand?->name) }}" required
                       placeholder="Ej: Rosanegra Polanco"
                       class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
                <textarea name="description" rows="2"
                          placeholder="Breve descripción de la marca..."
                          class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $brand?->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color de la Marca</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="color" value="{{ old('color', $brand?->color ?? '#4f46e5') }}"
                           class="h-10 w-16 rounded border-gray-300 dark:border-gray-600 cursor-pointer">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Se usa para identificar visualmente la marca en el panel</span>
                </div>
            </div>

            @if($brand)
                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active))
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Marca activa</span>
                    </label>
                </div>
            @endif

            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Contexto IA para esta Marca
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                    Instrucciones adicionales que la IA usará al responder conversaciones de esta marca.
                    Ej: tipo de cocina, horarios, políticas de reservación, tono de comunicación.
                </p>
                <textarea name="ai_context" rows="5"
                          placeholder="Ej: Rosanegra Polanco es un restaurante de cocina mediterránea ubicado en Av. Masaryk 330. Horario: Lun-Dom 13:00-01:00. Dress code: Smart casual. Acepta reservaciones por teléfono y OpenTable..."
                          class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500">{{ old('ai_context', $brand?->settings['ai_context'] ?? '') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('brands.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancelar</a>
                <button type="submit"
                        class="px-5 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    {{ $brand ? 'Guardar Cambios' : 'Crear Marca' }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
