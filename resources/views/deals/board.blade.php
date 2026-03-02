<x-app-layout>
    <div x-data="{ showCreateModal: false }">
        {{-- Header --}}
        <div class="px-4 py-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Negocios</h2>

                @if($pipelines->count() > 1)
                    <select onchange="window.location.href='{{ route('deals.board') }}?pipeline_id=' + this.value"
                            class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        @foreach($pipelines as $p)
                            <option value="{{ $p->id }}" @selected($activePipeline && $p->id === $activePipeline->id)>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                @elseif($activePipeline)
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $activePipeline->name }}</span>
                @endif
            </div>

            @can('create', App\Models\Deal::class)
                <button @click="showCreateModal = true"
                        class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    + Nuevo Negocio
                </button>
            @endcan
        </div>

        @if($activePipeline)
            {{-- Board --}}
            <div class="flex overflow-x-auto h-[calc(100vh-128px)] p-4 gap-4 bg-gray-100 dark:bg-gray-900">
                @foreach($stages as $stage)
                    @include('deals.partials.stage-column', ['stage' => $stage, 'allStages' => $stages])
                @endforeach
            </div>
        @else
            <div class="flex items-center justify-center h-[calc(100vh-128px)] bg-gray-100 dark:bg-gray-900">
                <p class="text-gray-500 dark:text-gray-400">No hay pipelines configurados.</p>
            </div>
        @endif

        {{-- Create deal modal --}}
        @can('create', App\Models\Deal::class)
            @if($activePipeline)
                @include('deals.partials.create-form', ['activePipeline' => $activePipeline, 'agents' => $agents])
            @endif
        @endcan

        {{-- Deal detail drawer --}}
        @isset($selectedDeal)
            @include('deals.partials.deal-drawer', ['deal' => $selectedDeal, 'dealStages' => $dealStages, 'agents' => $agents])
        @endisset
    </div>
</x-app-layout>
