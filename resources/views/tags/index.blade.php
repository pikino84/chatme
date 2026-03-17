<x-app-layout>
    <x-slot name="header">Etiquetas</x-slot>

    <div class="max-w-3xl mx-auto py-6 px-4">
        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-sm rounded">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 text-sm rounded">{{ session('error') }}</div>
        @endif

        {{-- Create form --}}
        @if(auth()->user()->hasPermissionTo('pipelines.create'))
            <form method="POST" action="{{ route('tags.store') }}"
                  class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-6">
                @csrf
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Nueva Etiqueta</h3>
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <input type="text" name="name" required placeholder="Nombre de la etiqueta"
                               class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <input type="color" name="color" value="#6B7280"
                               class="h-9 w-14 rounded border-gray-300 dark:border-gray-600 cursor-pointer" title="Color">
                    </div>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                        Crear
                    </button>
                </div>
            </form>
        @endif

        {{-- Tags list --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Etiqueta</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Negocios</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tags as $tag)
                        <tr id="tag-row-{{ $tag->id }}">
                            {{-- View mode --}}
                            <td class="px-5 py-3 tag-view-{{ $tag->id }}">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="inline-block w-3 h-3 rounded-full" style="background: {{ $tag->color }}"></span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $tag->name }}</span>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400 tag-view-{{ $tag->id }}">{{ $tag->deals_count }}</td>
                            <td class="px-5 py-3 text-right tag-view-{{ $tag->id }}">
                                <div class="flex items-center justify-end gap-2">
                                    @if(auth()->user()->hasPermissionTo('pipelines.update'))
                                        <button type="button" onclick="editTag({{ $tag->id }}, '{{ addslashes($tag->name) }}', '{{ $tag->color }}')"
                                                class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Editar</button>
                                    @endif
                                    @if(auth()->user()->hasPermissionTo('pipelines.delete'))
                                        <form method="POST" action="{{ route('tags.destroy', $tag) }}" class="inline" onsubmit="return confirm('¿Eliminar esta etiqueta?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>

                            {{-- Edit mode (hidden) --}}
                            <td colspan="3" class="px-5 py-3 hidden tag-edit-{{ $tag->id }}">
                                <form method="POST" action="{{ route('tags.update', $tag) }}" class="flex items-center gap-3">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ $tag->name }}" required
                                           class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500">
                                    <input type="color" name="color" value="{{ $tag->color }}"
                                           class="h-9 w-14 rounded border-gray-300 dark:border-gray-600 cursor-pointer">
                                    <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Guardar</button>
                                    <button type="button" onclick="cancelEdit({{ $tag->id }})" class="text-sm text-gray-500 hover:underline">Cancelar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No hay etiquetas creadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script>
    function editTag(id, name, color) {
        document.querySelectorAll('.tag-view-' + id).forEach(function(el) { el.classList.add('hidden'); });
        document.querySelectorAll('.tag-edit-' + id).forEach(function(el) { el.classList.remove('hidden'); });
    }
    function cancelEdit(id) {
        document.querySelectorAll('.tag-view-' + id).forEach(function(el) { el.classList.remove('hidden'); });
        document.querySelectorAll('.tag-edit-' + id).forEach(function(el) { el.classList.add('hidden'); });
    }
    </script>
    @endpush
</x-app-layout>
