<div class="deal-card bg-gray-50 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600 p-3 cursor-pointer hover:shadow-md transition-shadow"
     draggable="true"
     data-deal-id="{{ $deal->id }}"
     data-stage-id="{{ $deal->pipeline_stage_id }}"
     ondragstart="handleDragStart(event)"
     ondragend="handleDragEnd(event)"
     onclick="openDealDrawer({{ $deal->id }}, event)">
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
        <div class="flex flex-wrap gap-1 mt-2">
            @foreach($deal->tags as $tag)
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full text-white shadow-sm"
                      style="background: {{ $tag->color ?? '#6B7280' }}">
                    {{ $tag->name }}
                </span>
            @endforeach
        </div>
    @endif

    @if($deal->stage_entered_at)
        <div class="text-[10px] text-gray-400 mt-1">{{ $deal->stage_entered_at->diffForHumans() }}</div>
    @endif
</div>
