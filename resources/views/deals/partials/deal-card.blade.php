<div class="bg-gray-50 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600 p-3">
    <a href="{{ route('deals.show', $deal) }}" class="block">
        <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">{{ $deal->contact_name }}</div>

        @if($deal->value > 0)
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                ${{ number_format($deal->value, 2) }} {{ $deal->currency }}
            </div>
        @endif

        @if($deal->assignedUser)
            <div class="text-xs text-gray-400 mt-1">{{ $deal->assignedUser->name }}</div>
        @else
            <div class="text-xs text-gray-400 mt-1 italic">Sin asignar</div>
        @endif

        @if($deal->tags->count())
            <div class="flex flex-wrap gap-1 mt-1">
                @foreach($deal->tags as $tag)
                    <span class="text-[10px] px-1.5 py-0.5 rounded"
                          style="background: {{ $tag->color ?? '#6B7280' }}20; color: {{ $tag->color ?? '#6B7280' }}">
                        {{ $tag->name }}
                    </span>
                @endforeach
            </div>
        @endif

        @if($deal->stage_entered_at)
            <div class="text-[10px] text-gray-400 mt-1">{{ $deal->stage_entered_at->diffForHumans() }}</div>
        @endif
    </a>

    @can('update', $deal)
        <div x-data="{ showMove: false }" class="mt-2 border-t border-gray-200 dark:border-gray-600 pt-2">
            <button @click.prevent="showMove = !showMove" class="text-xs text-indigo-500 hover:text-indigo-700">
                Mover etapa
            </button>
            <form x-show="showMove" x-cloak method="POST" action="{{ route('deals.move', $deal) }}" class="mt-1 flex gap-1">
                @csrf
                <select name="pipeline_stage_id" class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 flex-1">
                    @foreach($allStages as $s)
                        <option value="{{ $s->id }}" @selected($s->id === $deal->pipeline_stage_id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="text-xs bg-indigo-500 text-white px-2 py-1 rounded hover:bg-indigo-600">Ir</button>
            </form>
        </div>
    @endcan
</div>
