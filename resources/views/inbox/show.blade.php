<x-app-layout>
    <div class="flex relative overflow-hidden sm:-m-6" style="height: calc(100vh - 64px);" id="conversation-container">

        {{-- Left: Conversation List (desktop only) --}}
        <div class="hidden lg:flex lg:w-80 border-r border-gray-200 dark:border-gray-700 flex-col bg-white dark:bg-gray-800 shrink-0">
            <div class="p-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Conversaciones</h2>
                <a href="{{ route('inbox') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    Bandeja completa &rarr;
                </a>
            </div>
            <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($conversations as $conv)
                    @include('inbox.partials.conversation-list', ['conv' => $conv])
                @endforeach
            </div>
        </div>

        {{-- Metadata Panel (hidden by default, opens on info click) --}}
        <div id="metadata-panel"
             class="fixed top-0 right-0 bottom-0 z-50
                    w-full lg:w-96
                    border-l border-gray-200 dark:border-gray-700
                    flex flex-col bg-white dark:bg-gray-800
                    shrink-0 overflow-y-auto
                    transform translate-x-full transition-transform duration-300">
            <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                <a href="{{ route('inbox', request()->only(['status', 'channel_id', 'assigned_user_id', 'search'])) }}"
                   class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    &larr; Volver a Bandeja
                </a>
            </div>
            @include('inbox.partials.metadata-drawer')
        </div>

        {{-- Center: Messages --}}
        <div class="flex-1 flex flex-col bg-gray-50 dark:bg-gray-900 min-w-0">
            {{-- Header --}}
            <div class="px-3 sm:px-4 py-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                {{-- Back to inbox (mobile only) --}}
                <a href="{{ route('inbox', request()->only(['status', 'channel_id', 'assigned_user_id', 'search'])) }}"
                   class="lg:hidden flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0 transition-colors active:scale-95">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
                {{-- Contact name --}}
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
                {{-- Info icon --}}
                <button onclick="toggleMetadataPanel()" class="flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0 transition-colors active:scale-95">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
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
        var isOpen = !panel.classList.contains('translate-x-full');
        if (isOpen) {
            panel.classList.add('translate-x-full');
        } else {
            panel.classList.remove('translate-x-full');
        }
    }

    window.openMediaModal = function(type, url, filename) {
        var modal = document.getElementById('media-modal');
        var body = document.getElementById('media-modal-body');
        var dl = document.getElementById('media-modal-download');
        var fname = document.getElementById('media-modal-filename');

        dl.href = url;
        dl.setAttribute('download', filename || '');
        fname.textContent = filename || '';

        if (type === 'image') {
            body.innerHTML = '<img src="' + url + '" class="max-w-full max-h-[80vh] rounded-lg object-contain" alt="' + (filename || '') + '">';
        } else if (type === 'video') {
            body.innerHTML = '<video controls autoplay class="max-w-full max-h-[80vh] rounded-lg"><source src="' + url + '">Tu navegador no soporta video.</video>';
        }

        modal.classList.remove('hidden');
        modal.offsetHeight;
        modal.querySelector('.media-modal-backdrop').classList.add('opacity-100');
        modal.querySelector('.media-modal-content').classList.add('scale-100', 'opacity-100');
    };

    window.closeMediaModal = function() {
        var modal = document.getElementById('media-modal');
        var backdrop = modal.querySelector('.media-modal-backdrop');
        var content = modal.querySelector('.media-modal-content');

        backdrop.classList.remove('opacity-100');
        content.classList.remove('scale-100', 'opacity-100');

        var video = modal.querySelector('video');
        if (video) video.pause();

        setTimeout(function() { modal.classList.add('hidden'); }, 200);
    };

    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeMediaModal(); });

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

        function docIconHtml(fileName) {
            var ext = (fileName || '').split('.').pop().toLowerCase();
            var iconColor, iconSvg;
            if (ext === 'pdf') {
                iconColor = 'text-red-500 bg-red-50';
                iconSvg = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg>';
            } else if (ext === 'doc' || ext === 'docx') {
                iconColor = 'text-blue-600 bg-blue-50';
                iconSvg = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6zm2-5h8v1.5H8V15zm0-3h8v1.5H8V12zm0-3h5v1.5H8V9z"/></svg>';
            } else if (ext === 'xls' || ext === 'xlsx' || ext === 'csv') {
                iconColor = 'text-green-600 bg-green-50';
                iconSvg = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6zm2-5h3v1.5H8V15zm5 0h3v1.5h-3V15zM8 12h3v1.5H8V12zm5 0h3v1.5h-3V12zM8 9h3v1.5H8V9zm5 0h3v1.5h-3V9z"/></svg>';
            } else if (ext === 'ppt' || ext === 'pptx') {
                iconColor = 'text-orange-500 bg-orange-50';
                iconSvg = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6zm3-7h2.5c1.1 0 2-.9 2-2s-.9-2-2-2H8v7h1.5v-3zm0-2.5h2c.28 0 .5.22.5.5s-.22.5-.5.5H9v-1z"/></svg>';
            } else if (ext === 'zip' || ext === 'rar' || ext === '7z' || ext === 'tar' || ext === 'gz') {
                iconColor = 'text-yellow-600 bg-yellow-50';
                iconSvg = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-2 6h-2v2h2v2h-2v2h-2v-2h2v-2h-2v-2h2v-2h-2V8h2v2h2v2z"/></svg>';
            } else {
                iconColor = 'text-gray-500 bg-gray-100';
                iconSvg = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
            }
            return { color: iconColor, svg: iconSvg };
        }

        function isMediaPlaceholder(body) {
            return ['[Image]', '[Audio]', '[Video]', '[Document]'].indexOf(body) !== -1;
        }

        function renderAttachment(att) {
            if (att.status === 'pending' || att.status === 'processing') {
                return '<div class="flex items-center gap-2 py-2 text-xs opacity-60"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Descargando ' + escapeHtml(att.media_type) + '...</div>';
            }
            if (att.status === 'failed') {
                return '<div class="flex items-center gap-2 py-2 text-xs text-red-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Error al descargar</div>';
            }
            if (att.media_type === 'image' && att.url) {
                return '<img src="' + escapeHtml(att.thumbnail_url || att.url) + '" alt="' + escapeHtml(att.file_name) + '" class="max-w-[250px] max-h-[250px] rounded-lg object-cover cursor-pointer hover:opacity-90 transition mb-1" loading="lazy" onclick="openMediaModal(\'image\', \'' + escapeHtml(att.url) + '\', \'' + escapeHtml(att.file_name) + '\')">';
            }
            if (att.media_type === 'video' && att.url) {
                return '<div class="relative cursor-pointer group mb-1" onclick="openMediaModal(\'video\', \'' + escapeHtml(att.url) + '\', \'' + escapeHtml(att.file_name) + '\')"><video preload="metadata" class="max-w-[280px] max-h-[250px] rounded-lg pointer-events-none"><source src="' + escapeHtml(att.url) + '" type="' + escapeHtml(att.mime_type) + '"></video><div class="absolute inset-0 flex items-center justify-center bg-black/20 rounded-lg group-hover:bg-black/30 transition"><svg class="w-12 h-12 text-white drop-shadow-lg" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div></div>';
            }
            if (att.media_type === 'audio' && att.url) {
                var dur = att.duration ? ' <span class="text-[10px] opacity-60">' + escapeHtml(att.duration) + '</span>' : '';
                return '<div class="flex items-center gap-2 mb-1 min-w-[200px]"><audio controls preload="metadata" class="h-10 w-full max-w-[250px]"><source src="' + escapeHtml(att.url) + '" type="' + escapeHtml(att.mime_type) + '"></audio>' + dur + '</div>';
            }
            if (att.media_type === 'document' && att.url) {
                var di = docIconHtml(att.file_name);
                return '<a href="' + escapeHtml(att.url) + '" target="_blank" class="flex items-center gap-3 p-2 rounded-lg bg-black/5 hover:bg-black/10 transition min-w-[180px] mb-1"><div class="w-10 h-10 rounded-lg ' + di.color + ' flex items-center justify-center shrink-0">' + di.svg + '</div><div class="min-w-0 flex-1"><p class="text-xs font-medium truncate">' + escapeHtml(att.file_name) + '</p><p class="text-[10px] opacity-60">' + escapeHtml(att.file_size) + '</p></div></a>';
            }
            return '';
        }

        function renderAttachments(attachments) {
            if (!attachments || !attachments.length) return '';
            var html = '';
            attachments.forEach(function(att) { html += renderAttachment(att); });
            return html;
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
            var time = msg.time || '';

            var hasMedia = msg.attachments && msg.attachments.length > 0;
            var bodyHtml = '';
            if (hasMedia) {
                bodyHtml = renderAttachments(msg.attachments);
                if (msg.body && !isMediaPlaceholder(msg.body)) {
                    bodyHtml += '<p class="mt-1 text-xs opacity-70">' + escapeHtml(msg.body) + '</p>';
                }
            } else {
                bodyHtml = escapeHtml(msg.body);
            }

            if (msg.type === 'internal_note') {
                div.className = 'flex justify-center';
                div.innerHTML = '<div class="max-w-md px-3 py-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-xs text-yellow-700 dark:text-yellow-300">' +
                    '<span class="font-medium">' + escapeHtml(msg.user_name || 'System') + ':</span> ' +
                    escapeHtml(msg.body) + ' <span class="text-yellow-400 ml-2">' + time + '</span></div>';
            } else if (msg.direction === 'inbound') {
                div.className = 'flex justify-start';
                div.innerHTML = '<div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-bl-sm bg-white dark:bg-gray-700 shadow-sm text-sm text-gray-800 dark:text-gray-200">' +
                    bodyHtml + '<div class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 text-right">' + time + '</div></div>';
            } else {
                div.className = 'flex justify-end';
                div.innerHTML = '<div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-crea-primary text-white text-sm shadow-sm">' +
                    bodyHtml + '<div class="text-[10px] text-crea-secondary-light mt-1 text-right">' + escapeHtml(msg.user_name || 'Agent') + ' &middot; ' + time + '</div></div>';
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

    {{-- Media Modal --}}
    <div id="media-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center" onclick="if(event.target===this||event.target.classList.contains('media-modal-backdrop'))closeMediaModal()">
        <div class="media-modal-backdrop absolute inset-0 bg-black/80 opacity-0 transition-opacity duration-200"></div>
        <div class="media-modal-content relative z-10 max-w-[90vw] max-h-[90vh] flex flex-col items-center scale-95 opacity-0 transition-all duration-200">
            <div class="flex items-center justify-between w-full mb-3 px-1">
                <span id="media-modal-filename" class="text-white text-sm truncate max-w-[60vw]"></span>
                <div class="flex items-center gap-3">
                    <a id="media-modal-download" href="#" download class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a2 2 0 002 2h14a2 2 0 002-2v-3"/></svg>
                        Descargar
                    </a>
                    <button onclick="closeMediaModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div id="media-modal-body" class="flex items-center justify-center"></div>
        </div>
    </div>
</x-app-layout>
