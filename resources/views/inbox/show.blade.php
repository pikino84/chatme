<x-app-layout>
    <div class="flex h-[calc(100vh-64px)]" x-data="conversationApp()" x-init="init()">
        {{-- Left: Back + Conversation Info --}}
        <div class="w-80 border-r border-gray-200 dark:border-gray-700 flex flex-col bg-white dark:bg-gray-800 shrink-0 overflow-y-auto">
            <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                <a href="{{ route('inbox', request()->only(['status', 'channel_id', 'assigned_user_id', 'search'])) }}"
                   class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    &larr; Back to Inbox
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
                    This conversation is closed.
                    @can('reopen', $conversation)
                        <form method="POST" action="{{ route('inbox.conversations.reopen', $conversation) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 underline">Reopen</button>
                        </form>
                    @endcan
                </div>
            @endif
        </div>
    </div>

    @push('modals')
    <script>
    function conversationApp() {
        return {
            orgId: {{ $conversation->organization_id }},
            convId: {{ $conversation->id }},
            init() {
                if (!window.Echo) return;

                window.Echo.private(`conversation.${this.orgId}.${this.convId}`)
                    .listen('MessageReceivedEvent', () => { window.location.reload(); });

                window.Echo.private(`organization.${this.orgId}`)
                    .listen('ConversationAssignedEvent', (e) => {
                        if (e.conversation_id === this.convId) window.location.reload();
                    })
                    .listen('ConversationClosedEvent', (e) => {
                        if (e.conversation_id === this.convId) window.location.reload();
                    });
            }
        }
    }
    </script>
    @endpush
</x-app-layout>
