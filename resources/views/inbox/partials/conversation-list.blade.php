<a href="{{ route('inbox.conversations.show', $conv) }}"
   class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition {{ request()->route('conversation')?->id === $conv->id ? 'bg-indigo-50 dark:bg-indigo-900/20 border-l-2 border-indigo-500' : '' }}">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
            {{ $conv->contact_name ?: $conv->contact_identifier }}
        </span>
        <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0 ml-2">
            {{ $conv->last_message_at?->diffForHumans(short: true) ?? $conv->created_at->diffForHumans(short: true) }}
        </span>
    </div>

    <div class="flex items-center justify-between mt-1">
        <span class="text-xs text-gray-500 dark:text-gray-400 truncate">
            @if($conv->messages->first())
                {{ Str::limit($conv->messages->first()->body, 50) }}
            @else
                Sin mensajes aún
            @endif
        </span>

        <div class="flex items-center gap-1 shrink-0 ml-2">
            @if($conv->status === 'open')
                <span class="inline-block w-2 h-2 rounded-full bg-green-400"></span>
            @elseif($conv->status === 'pending')
                <span class="inline-block w-2 h-2 rounded-full bg-yellow-400"></span>
            @else
                <span class="inline-block w-2 h-2 rounded-full bg-gray-300"></span>
            @endif

            <span class="text-[10px] px-1.5 py-0.5 rounded
                @if($conv->channel->type === 'whatsapp') bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300
                @elseif($conv->channel->type === 'webchat') bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300
                @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300
                @endif">
                {{ ucfirst($conv->channel->type) }}
            </span>
        </div>
    </div>

    @if($conv->assignedUser)
        <div class="mt-1 text-[10px] text-gray-400 dark:text-gray-500 truncate">
            {{ $conv->assignedUser->name }}
        </div>
    @endif
</a>
