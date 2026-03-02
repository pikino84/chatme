<div class="flex-shrink-0 w-72 bg-white dark:bg-gray-800 rounded-lg shadow flex flex-col max-h-full">
    <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div class="flex items-center">
            <span class="inline-block w-2 h-2 rounded-full mr-2" style="background: {{ $stage->color ?? '#6B7280' }}"></span>
            <span class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ $stage->name }}</span>
        </div>
        <span class="text-xs text-gray-400">{{ $stage->deals->count() }}</span>
    </div>

    <div class="flex-1 overflow-y-auto p-2 space-y-2">
        @forelse($stage->deals as $deal)
            @include('deals.partials.deal-card', ['deal' => $deal, 'allStages' => $allStages])
        @empty
            <p class="text-xs text-gray-400 dark:text-gray-500 text-center py-4">Sin negocios</p>
        @endforelse
    </div>
</div>
