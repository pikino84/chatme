<x-app-layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('brands.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">&larr; Marcas</a>
                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                     style="background-color: {{ $brand->color }}">
                    {{ strtoupper(substr($brand->name, 0, 2)) }}
                </div>
                <div>
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $brand->name }}</h2>
                    @if(!$brand->is_active)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Inactiva</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $brand)
                    <a href="{{ route('brands.edit', $brand) }}"
                       class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                        Editar
                    </a>
                @endcan
                @can('delete', $brand)
                    <form action="{{ route('brands.destroy', $brand) }}" method="POST"
                          onsubmit="return confirm('¿Eliminar esta marca?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-3 py-1.5 text-sm text-red-600 border border-red-300 rounded-md hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                            Eliminar
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm rounded">
                {{ session('error') }}
            </div>
        @endif

        @if($brand->description)
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ $brand->description }}</p>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                <div class="text-2xl font-bold text-indigo-600">{{ $brand->branches->count() }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sucursales</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                <div class="text-2xl font-bold text-green-600">{{ $brand->channels->count() }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Canales</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $brand->conversations_count }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Conversaciones</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                <div class="text-2xl font-bold text-amber-600">{{ $brand->kb_articles_count }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Artículos KB</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Channels --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Canales</h3>
                    <a href="{{ route('settings.channels') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Gestionar</a>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($brand->channels as $channel)
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                @if($channel->type === 'whatsapp')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">WA</span>
                                @elseif($channel->type === 'facebook')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">FB</span>
                                @elseif($channel->type === 'instagram')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300">IG</span>
                                @else
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Web</span>
                                @endif
                                <span class="text-sm text-gray-900 dark:text-gray-100">{{ $channel->name }}</span>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $channel->conversations_count }} conv.</span>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            Sin canales asignados a esta marca.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Branches --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Sucursales</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($brand->branches as $branch)
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div>
                                <span class="text-sm text-gray-900 dark:text-gray-100">{{ $branch->name }}</span>
                                @if($branch->address)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $branch->address }}</p>
                                @endif
                            </div>
                            @if($branch->is_active)
                                <span class="text-xs text-green-600 dark:text-green-400">Activa</span>
                            @else
                                <span class="text-xs text-gray-400">Inactiva</span>
                            @endif
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            Sin sucursales asignadas a esta marca.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- KB Categories --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden lg:col-span-2">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Base de Conocimiento</h3>
                    <a href="{{ route('kb.articles') }}?brand_id={{ $brand->id }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Ver artículos</a>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($brand->kbCategories as $cat)
                        <div class="px-4 py-3 flex items-center justify-between">
                            <span class="text-sm text-gray-900 dark:text-gray-100">{{ $cat->name }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $cat->articles_count }} artículos</span>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            Sin categorías KB específicas de esta marca.
                            <p class="text-xs text-gray-400 mt-1">Los artículos sin marca se consideran globales y están disponibles para todas las marcas.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- AI Context --}}
        @if($brand->getAiContext())
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Contexto IA</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $brand->getAiContext() }}</p>
            </div>
        @endif
    </div>
</x-app-layout>
