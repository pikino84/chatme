<x-app-layout>
    <x-slot name="header">Pipelines</x-slot>

    <div class="max-w-5xl mx-auto py-6 px-4">
        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-sm rounded">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 text-sm rounded">{{ session('error') }}</div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Pipelines de Ventas</h2>
            @can('create', App\Models\Pipeline::class)
                <a href="{{ route('pipelines.create') }}"
                   class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    + Nuevo Pipeline
                </a>
            @endcan
        </div>

        <div class="space-y-4">
            @forelse($pipelines as $pipeline)
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">{{ $pipeline->name }}</h3>
                            @if($pipeline->is_default)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">Predeterminado</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ $pipeline->stages_count }} etapas</span>
                            <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $pipeline->deals_count }} negocios</span>
                        </div>
                    </div>

                    {{-- Stage preview --}}
                    <div class="flex items-center gap-1 mb-4 overflow-x-auto pb-1">
                        @foreach($pipeline->stages()->orderBy('position')->get() as $stage)
                            <div class="flex items-center gap-1 shrink-0">
                                @if(!$loop->first)
                                    <svg class="w-4 h-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                @endif
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-full border
                                    {{ $stage->is_won ? 'bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300' : ($stage->is_lost ? 'bg-red-50 dark:bg-red-900/30 border-red-200 dark:border-red-800 text-red-700 dark:text-red-300' : 'bg-gray-50 dark:bg-gray-700 border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300') }}">
                                    <span class="inline-block w-2 h-2 rounded-full" style="background: {{ $stage->color ?? '#6B7280' }}"></span>
                                    {{ $stage->name }}
                                    @if($stage->max_duration_hours)
                                        <span class="text-gray-400 dark:text-gray-500">({{ $stage->max_duration_hours }}h)</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-3">
                        @can('update', $pipeline)
                            @if(!$pipeline->is_default)
                                <form method="POST" action="{{ route('pipelines.set-default', $pipeline) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Hacer predeterminado</button>
                                </form>
                            @endif
                            <a href="{{ route('pipelines.edit', $pipeline) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Editar</a>
                        @endcan
                        @can('delete', $pipeline)
                            @if($pipeline->deals_count === 0)
                                <form method="POST" action="{{ route('pipelines.destroy', $pipeline) }}" class="inline" onsubmit="return confirm('¿Eliminar este pipeline y todas sus etapas?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-gray-500 dark:text-gray-400">No hay pipelines configurados.</p>
                    @can('create', App\Models\Pipeline::class)
                        <a href="{{ route('pipelines.create') }}" class="mt-2 inline-block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Crear el primer pipeline</a>
                    @endcan
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
