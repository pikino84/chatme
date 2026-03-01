<x-app-layout>
    <div class="flex h-[calc(100vh-64px)]" x-data="inboxApp()" x-init="init()">
        {{-- Left: Conversation List --}}
        <div class="w-80 border-r border-gray-200 dark:border-gray-700 flex flex-col bg-white dark:bg-gray-800 shrink-0">
            @include('inbox.partials.filters')

            <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($conversations as $conv)
                    @include('inbox.partials.conversation-list', ['conv' => $conv])
                @empty
                    <div class="p-6 text-center text-gray-500 dark:text-gray-400 text-sm">
                        No conversations found.
                    </div>
                @endforelse
            </div>

            <div class="p-2 border-t border-gray-200 dark:border-gray-700 text-xs">
                {{ $conversations->links('pagination::simple-tailwind') }}
            </div>
        </div>

        {{-- Center: Message Thread --}}
        <div class="flex-1 flex flex-col bg-gray-50 dark:bg-gray-900">
            @if(request('active'))
                {{-- Loaded via show route --}}
            @else
                <div class="flex-1 flex items-center justify-center text-gray-400 dark:text-gray-500">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <p class="text-sm">Select a conversation to start</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('modals')
    <script>
    function inboxApp() {
        return {
            orgId: {{ auth()->user()->organization_id ?? 'null' }},
            init() {
                if (!window.Echo || !this.orgId) return;

                window.Echo.private(`organization.${this.orgId}`)
                    .listen('ConversationCreated', () => { window.location.reload(); })
                    .listen('ConversationAssignedEvent', () => { window.location.reload(); })
                    .listen('ConversationClosedEvent', () => { window.location.reload(); });
            }
        }
    }
    </script>
    @endpush
</x-app-layout>
