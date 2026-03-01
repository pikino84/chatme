<div class="flex-1 overflow-y-auto p-4 space-y-3" id="message-thread">
    @foreach($messages as $msg)
        @if($msg->isInternalNote())
            <div class="flex justify-center">
                <div class="max-w-md px-3 py-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-xs text-yellow-700 dark:text-yellow-300">
                    <span class="font-medium">{{ $msg->user?->name ?? 'System' }}:</span>
                    {{ $msg->body }}
                    <span class="text-yellow-400 ml-2">{{ $msg->created_at->format('H:i') }}</span>
                </div>
            </div>
        @elseif($msg->isInbound())
            <div class="flex justify-start">
                <div class="max-w-md px-4 py-2 rounded-2xl rounded-bl-sm bg-white dark:bg-gray-700 shadow-sm text-sm text-gray-800 dark:text-gray-200">
                    {{ $msg->body }}
                    <div class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 text-right">
                        {{ $msg->created_at->format('H:i') }}
                    </div>
                </div>
            </div>
        @else
            <div class="flex justify-end">
                <div class="max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-indigo-600 text-white text-sm shadow-sm">
                    {{ $msg->body }}
                    <div class="text-[10px] text-indigo-200 mt-1 text-right">
                        {{ $msg->user?->name ?? 'Agent' }} &middot; {{ $msg->created_at->format('H:i') }}
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('message-thread');
        if (el) el.scrollTop = el.scrollHeight;
    });
</script>
