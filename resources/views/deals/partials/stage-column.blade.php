<div class="flex-shrink-0 bg-white dark:bg-gray-800 rounded-lg shadow flex flex-col max-h-full stage-column transition-all duration-300 w-72"
     data-stage-col="{{ $stage->id }}">
    {{-- Expanded header --}}
    <div class="stage-header-expanded px-3 py-2 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between cursor-pointer"
         onclick="toggleStageColumn({{ $stage->id }})"
         title="Contraer columna">
        <div class="flex items-center min-w-0">
            <span class="inline-block w-2 h-2 rounded-full mr-2 shrink-0" style="background: {{ $stage->color ?? '#6B7280' }}"></span>
            <span class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">{{ $stage->name }}</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 stage-count" data-stage-id="{{ $stage->id }}">{{ $stage->deals->count() }}</span>
            <svg class="w-4 h-4 text-gray-400 shrink-0 chevron-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </div>
    </div>

    {{-- Collapsed header (vertical bar) --}}
    <div class="stage-header-collapsed hidden cursor-pointer flex-1 flex flex-col items-center py-3 gap-2"
         onclick="toggleStageColumn({{ $stage->id }})"
         title="Expandir columna: {{ $stage->name }}">
        <span class="inline-block w-2 h-2 rounded-full shrink-0" style="background: {{ $stage->color ?? '#6B7280' }}"></span>
        <span class="text-xs font-medium text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-full px-2 py-0.5 stage-count-collapsed">{{ $stage->deals->count() }}</span>
        <span class="font-medium text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap stage-name-vertical"
              style="writing-mode: vertical-rl; text-orientation: mixed;">{{ $stage->name }}</span>
        <svg class="w-4 h-4 text-gray-400 shrink-0 mt-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </div>

    {{-- Deals area (hidden when collapsed) --}}
    <div class="stage-body flex-1 overflow-y-auto p-2 space-y-2 drop-zone min-h-[60px]"
         data-stage-id="{{ $stage->id }}"
         ondragover="handleDragOver(event)"
         ondragenter="handleDragEnter(event)"
         ondragleave="handleDragLeave(event)"
         ondrop="handleDrop(event)">
        @forelse($stage->deals as $deal)
            @include('deals.partials.deal-card', ['deal' => $deal, 'allStages' => $allStages])
        @empty
            <p class="text-xs text-gray-400 dark:text-gray-500 text-center py-4 empty-msg">Sin negocios</p>
        @endforelse
    </div>
</div>
