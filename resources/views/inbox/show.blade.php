<x-app-layout>
    <div class="flex h-[calc(100vh-64px)]" id="conversation-container">
        {{-- Left: Back + Conversation Info --}}
        <div class="w-80 border-r border-gray-200 dark:border-gray-700 flex flex-col bg-white dark:bg-gray-800 shrink-0 overflow-y-auto">
            <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                <a href="{{ route('inbox', request()->only(['status', 'channel_id', 'assigned_user_id', 'search'])) }}"
                   class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    &larr; Volver a Bandeja
                </a>
            </div>
            @include('inbox.partials.metadata-drawer')
        </div>

        {{-- Center: Messages --}}
        <div class="flex-1 flex flex-col bg-gray-50 dark:bg-gray-900">
            {{-- Header --}}
            <div class="px-4 py-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $conversation->contact_name ?: $conversation->contact_identifier }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ ucfirst($conversation->channel->type) }} &middot; {{ ucfirst($conversation->status) }}
                        @if($conversation->subject)
                            &middot; {{ $conversation->subject }}
                        @endif
                    </p>
                </div>
            </div>

            {{-- Messages --}}
            @include('inbox.partials.message-thread')

            {{-- Input --}}
            @if($conversation->isOpen())
                @include('inbox.partials.message-input')
            @else
                <div class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 text-center text-xs text-gray-500 dark:text-gray-400">
                    Esta conversación está cerrada.
                    @can('reopen', $conversation)
                        <form method="POST" action="{{ route('inbox.conversations.reopen', $conversation) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 underline">Reabrir</button>
                        </form>
                    @endcan
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
    (function() {
        var orgId = {{ $conversation->organization_id }};
        var convId = {{ $conversation->id }};
        var lastMessageCount = {{ $messages->total() }};
        window.__chatmeMessageCount = lastMessageCount;
        var pollUrl = '{{ route('inbox.conversations.messages.poll', $conversation) }}';
        var echoConnected = false;

        // Try Echo/WebSocket for real-time updates
        function initEcho() {
            if (!window.Echo) return;

            try {
                window.Echo.private('conversation.' + orgId + '.' + convId)
                    .listen('MessageReceivedEvent', function() { window.location.reload(); });

                window.Echo.private('organization.' + orgId)
                    .listen('ConversationAssignedEvent', function(e) {
                        if (e.conversation_id === convId) window.location.reload();
                    })
                    .listen('ConversationClosedEvent', function(e) {
                        if (e.conversation_id === convId) window.location.reload();
                    });

                echoConnected = true;
            } catch (err) {
                console.warn('Echo connection failed, using polling fallback');
            }
        }

        // Polling fallback: check for new messages every 5 seconds
        function startPolling() {
            setInterval(function() {
                fetch(pollUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.count > window.__chatmeMessageCount) {
                        window.location.reload();
                    }
                })
                .catch(function() { /* silent fail */ });
            }, 5000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            initEcho();
            // Always use polling as fallback (Echo/Reverb may not be running in production)
            startPolling();
        });
    })();
    </script>
    @endpush
</x-app-layout>
