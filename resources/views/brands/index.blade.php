<x-app-layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Marcas</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestiona tus marcas, sucursales y canales por marca.</p>
            </div>
            @can('create', App\Models\Brand::class)
                <a href="{{ route('brands.create') }}"
                   class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    + Nueva Marca
                </a>
            @endcan
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        @if($brands->isEmpty())
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <p class="text-gray-500 dark:text-gray-400">No hay marcas configuradas.</p>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Las marcas te permiten organizar sucursales, canales y KB por negocio.</p>
                @can('create', App\Models\Brand::class)
                    <a href="{{ route('brands.create') }}" class="mt-3 inline-block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        Crear tu primera marca
                    </a>
                @endcan
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($brands as $brand)
                    <a href="{{ route('brands.show', $brand) }}"
                       class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5 hover:border-indigo-300 dark:hover:border-indigo-600 transition group">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                                 style="background-color: {{ $brand->color }}">
                                {{ strtoupper(substr($brand->name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                    {{ $brand->name }}
                                </h3>
                                @if(!$brand->is_active)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Inactiva</span>
                                @endif
                            </div>
                        </div>

                        @if($brand->description)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 line-clamp-2">{{ $brand->description }}</p>
                        @endif

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1.5">
                                <span class="text-gray-500 dark:text-gray-400">Sucursales</span>
                                <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ $brand->branches_count }}</span>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1.5">
                                <span class="text-gray-500 dark:text-gray-400">Canales</span>
                                <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ $brand->channels_count }}</span>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1.5">
                                <span class="text-gray-500 dark:text-gray-400">Conversaciones</span>
                                <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ $brand->conversations_count }}</span>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1.5">
                                <span class="text-gray-500 dark:text-gray-400">KB Artículos</span>
                                <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ $brand->kb_articles_count }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
