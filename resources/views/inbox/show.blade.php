<x-app-layout>
    <div class="flex h-[calc(100vh-64px)] relative" id="conversation-container">
        {{-- Left: Back + Conversation Info (hidden on mobile by default) --}}
        <div id="metadata-panel"
             class="fixed inset-0 z-40 lg:relative lg:z-auto
                    w-full lg:w-80
                    border-r border-gray-200 dark:border-gray-700
                    flex flex-col bg-white dark:bg-gray-800
                    shrink-0 overflow-y-auto
                    transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
            {{-- Mobile close button --}}
            <div class="flex items-center justify-between p-3 border-b border-gray-200 dark:border-gray-700">
                <a href="{{ route('inbox', request()->only(['status', 'channel_id', 'assigned_user_id', 'search'])) }}"
                   class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    &larr; Volver a Bandeja
                </a>
                <button onclick="toggleMetadataPanel()" class="lg:hidden flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @include('inbox.partials.metadata-drawer')
        </div>

        {{-- Mobile overlay --}}
        <div id="metadata-overlay" class="fixed inset-0 bg-black/30 z-30 hidden lg:hidden" onclick="toggleMetadataPanel()"></div>

        {{-- Center: Messages --}}
        <div class="flex-1 flex flex-col bg-gray-50 dark:bg-gray-900 min-w-0">
            {{-- Header --}}
            <div class="px-3 sm:px-4 py-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                {{-- Mobile: info toggle button --}}
                <button onclick="toggleMetadataPanel()" class="lg:hidden flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                        {{ $conversation->contact_name ?: $conversation->contact_identifier }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
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
    function toggleMetadataPanel() {
        var panel = document.getElementById('metadata-panel');
        var overlay = document.getElementById('metadata-overlay');
        var isOpen = !panel.classList.contains('-translate-x-full');
        if (isOpen) {
            panel.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        } else {
            panel.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        }
    }

    (function() {
        var orgId = {{ $conversation->organization_id }};
        var convId = {{ $conversation->id }};
        var pollUrl = '{{ route('inbox.conversations.messages.poll', $conversation) }}';

        var lastMsgId = 0;
        var sentIds = {};
        window.__chatmeSentIds = sentIds;
        var echoConnected = false;

        function initLastId() {
            var thread = document.getElementById('message-thread');
            if (!thread) return;
            var items = thread.querySelectorAll('[data-msg-id]');
            if (items.length) {
                lastMsgId = parseInt(items[items.length - 1].getAttribute('data-msg-id'), 10) || 0;
            }
        }

        function escapeHtml(text) {
            var d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        function appendMessage(msg) {
            if (document.querySelector('[data-msg-id="' + msg.id + '"]')) return;
            if (sentIds[msg.id]) {
                delete sentIds[msg.id];
                return;
            }

            var thread = document.getElementById('message-thread');
            if (!thread) return;

            var div = document.createElement('div');
            div.setAttribute('data-msg-id', msg.id);
            var safeBody = escapeHtml(msg.body);
            var time = msg.time || '';

            if (msg.type === 'internal_note') {
                div.className = 'flex justify-center';
                div.innerHTML = '<div class="max-w-md px-3 py-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-xs text-yellow-700 dark:text-yellow-300">' +
                    '<span class="font-medium">' + escapeHtml(msg.user_name || 'System') + ':</span> ' +
                    safeBody + ' <span class="text-yellow-400 ml-2">' + time + '</span></div>';
            } else if (msg.direction === 'inbound') {
                div.className = 'flex justify-start';
                div.innerHTML = '<div class="max-w-md px-4 py-2 rounded-2xl rounded-bl-sm bg-white dark:bg-gray-700 shadow-sm text-sm text-gray-800 dark:text-gray-200">' +
                    safeBody + '<div class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 text-right">' + time + '</div></div>';
            } else {
                div.className = 'flex justify-end';
                div.innerHTML = '<div class="max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-indigo-600 text-white text-sm shadow-sm">' +
                    safeBody + '<div class="text-[10px] text-indigo-200 mt-1 text-right">' + escapeHtml(msg.user_name || 'Agent') + ' &middot; ' + time + '</div></div>';
            }

            thread.appendChild(div);
            thread.scrollTop = thread.scrollHeight;
            if (msg.id > lastMsgId) lastMsgId = msg.id;
        }

        function pollMessages() {
            var url = pollUrl + '?after_id=' + lastMsgId;
            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.messages && data.messages.length) {
                    data.messages.forEach(function(msg) {
                        appendMessage(msg);
                    });
                }
            })
            .catch(function() { /* silent */ });
        }

        function initEcho() {
            if (!window.Echo) return false;
            try {
                window.Echo.private('conversation.' + orgId + '.' + convId)
                    .listen('MessageReceivedEvent', function(e) { appendMessage(e); })
                    .listen('MessageSentEvent', function(e) { appendMessage(e); });

                window.Echo.private('organization.' + orgId)
                    .listen('ConversationAssignedEvent', function(e) {
                        if (e.conversation_id === convId) window.location.reload();
                    })
                    .listen('ConversationClosedEvent', function(e) {
                        if (e.conversation_id === convId) window.location.reload();
                    });

                echoConnected = true;
                return true;
            } catch (err) {
                console.warn('[ChatMe] Echo init failed, using polling fallback', err);
                return false;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            initLastId();
            var wsOk = initEcho();
            var interval = wsOk ? 30000 : 5000;
            setInterval(pollMessages, interval);
        });
    })();
    </script>
    @endpush
</x-app-layout>
