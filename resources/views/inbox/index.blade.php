<x-app-layout>
    <div class="flex overflow-hidden sm:-m-6" style="height: calc(100vh - 64px);" x-data="inboxApp()" x-init="init()">
        {{-- Left: Conversation List --}}
        <div class="w-full lg:w-80 border-r border-gray-200 dark:border-gray-700 flex flex-col bg-white dark:bg-gray-800 shrink-0">
            @include('inbox.partials.filters')

            <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700" id="conversation-list">
                @forelse($conversations as $conv)
                    <a href="{{ route('inbox.conversations.show', $conv) }}"
                       data-conv-id="{{ $conv->id }}"
                       onclick="return loadConversation({{ $conv->id }}, event)"
                       class="conv-item block px-4 py-3 hover:bg-gray-50 transition {{ $conv->unread_count > 0 ? 'bg-crea-secondary/5 border-l-2 border-crea-secondary' : '' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-sm {{ $conv->unread_count > 0 ? 'font-bold text-gray-900' : 'font-medium text-gray-900' }} truncate">
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
                        <div class="flex items-center justify-between mt-1">
                            @if($conv->assignedUser)
                                <span class="text-[10px] text-gray-400 truncate">
                                    {{ $conv->assignedUser->name }}
                                </span>
                            @else
                                <span></span>
                            @endif
                            @if($conv->unread_count > 0)
                                <span class="bg-crea-secondary text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 shrink-0">
                                    {{ $conv->unread_count > 99 ? '99+' : $conv->unread_count }}
                                </span>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="p-6 text-center text-gray-500 dark:text-gray-400 text-sm">
                        No se encontraron conversaciones.
                    </div>
                @endforelse
            </div>

            <div class="p-2 border-t border-gray-200 dark:border-gray-700 text-xs">
                {{ $conversations->links('pagination::simple-tailwind') }}
            </div>
        </div>

        {{-- Right: Chat Panel (desktop only) --}}
        <div class="hidden lg:flex flex-1 flex-col bg-gray-50 dark:bg-gray-900 min-w-0" id="chat-panel">
            {{-- Empty state --}}
            <div id="chat-empty" class="flex-1 flex items-center justify-center text-gray-400 dark:text-gray-500">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <p class="text-sm">Selecciona una conversación para comenzar</p>
                </div>
            </div>
            {{-- Loaded chat --}}
            <div id="chat-loaded" class="hidden flex-1 flex flex-col min-h-0"></div>
        </div>
    </div>

    @push('scripts')
    <script>
    var currentConvId = null;
    var csrfToken = '{{ csrf_token() }}';
    var pollInterval = null;
    var lastMsgId = 0;

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function loadConversation(convId, event) {
        // On mobile, navigate normally
        if (window.innerWidth < 1024) return true;
        event.preventDefault();

        // Highlight active conversation
        document.querySelectorAll('.conv-item').forEach(function(el) {
            el.classList.remove('bg-crea-secondary/10', 'border-l-2', 'border-crea-secondary');
        });
        var clicked = document.querySelector('[data-conv-id="' + convId + '"]');
        if (clicked) clicked.classList.add('bg-crea-secondary/10', 'border-l-2', 'border-crea-secondary');

        // Show loading
        document.getElementById('chat-empty').classList.add('hidden');
        var loaded = document.getElementById('chat-loaded');
        loaded.classList.remove('hidden');
        loaded.innerHTML = '<div class="flex-1 flex items-center justify-center text-gray-400">Cargando...</div>';

        // Fetch conversation
        fetch('/inbox/conversations/' + convId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            currentConvId = convId;
            renderChat(data);
            setupPolling(data.conversation.poll_url);
            // Mark as read
            fetch('/inbox/conversations/' + convId + '/read', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            }).catch(function() {});
            // Update URL without reload
            history.pushState(null, '', '/inbox/conversations/' + convId);
        })
        .catch(function() {
            loaded.innerHTML = '<div class="flex-1 flex items-center justify-center text-red-500">Error al cargar la conversación</div>';
        });

        return false;
    }

    function renderChat(data) {
        var c = data.conversation;
        var loaded = document.getElementById('chat-loaded');
        var html = '';

        // Header
        html += '<div class="px-4 py-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">';
        html += '<div class="min-w-0 flex-1">';
        html += '<h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">' + esc(c.contact_name || c.contact_identifier) + '</h3>';
        html += '<p class="text-xs text-gray-500 dark:text-gray-400 truncate">' + esc(c.channel_type.charAt(0).toUpperCase() + c.channel_type.slice(1)) + ' · ' + esc(c.status.charAt(0).toUpperCase() + c.status.slice(1));
        if (c.subject) html += ' · ' + esc(c.subject);
        html += '</p></div>';
        html += '<button onclick="openInfoPanel(' + c.id + ')" class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0 transition-colors">';
        html += '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        html += '</button></div>';

        // Messages
        html += '<div class="flex-1 overflow-y-auto p-4 space-y-3" id="message-thread">';
        data.messages.forEach(function(msg) {
            html += renderMessage(msg);
        });
        html += '</div>';

        // Input
        if (c.is_open) {
            html += '<div class="border-t border-gray-200 bg-white p-3">';
            html += '<div id="ajax-file-preview-' + c.id + '" class="hidden mb-2 p-2 rounded-lg bg-gray-50 border border-gray-200"><div class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-gray-200 flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div><div class="flex-1 min-w-0"><p id="ajax-file-name-' + c.id + '" class="text-xs font-medium text-gray-700 truncate"></p><p id="ajax-file-size-' + c.id + '" class="text-[10px] text-gray-400"></p></div><button type="button" onclick="clearAjaxFile(' + c.id + ')" class="w-7 h-7 rounded-full bg-red-100 text-red-500 hover:bg-red-200 flex items-center justify-center shrink-0"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div></div>';
            html += '<form onsubmit="sendMessage(event, ' + c.id + ')" class="flex gap-2 items-end">';
            html += '<input type="file" id="ajax-file-' + c.id + '" class="hidden" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip" onchange="onAjaxFileSelect(' + c.id + ')">';
            html += '<button type="button" onclick="document.getElementById(\'ajax-file-' + c.id + '\').click()" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 flex items-center justify-center shrink-0 transition" title="Adjuntar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg></button>';
            html += '<div class="flex-1"><textarea id="msg-body-' + c.id + '" rows="1" required placeholder="Escribe un mensaje..." class="w-full text-sm rounded-lg border-gray-300 focus:ring-crea-secondary focus:border-crea-secondary resize-none"></textarea></div>';
            html += '<div class="flex flex-col gap-1">';
            html += '<button type="submit" class="px-3 py-2 text-xs font-medium text-white bg-crea-primary hover:bg-crea-secondary rounded-lg">Enviar</button>';
            html += '<button type="button" onclick="sendNote(' + c.id + ')" class="px-3 py-2 text-xs font-medium text-yellow-700 bg-yellow-100 hover:bg-yellow-200 rounded-lg">Nota</button>';
            html += '</div></form></div>';
        } else {
            html += '<div class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 text-center text-xs text-gray-500">Esta conversación está cerrada.</div>';
        }

        loaded.innerHTML = html;

        // Scroll to bottom
        var thread = document.getElementById('message-thread');
        if (thread) thread.scrollTop = thread.scrollHeight;

        // Track last message ID
        lastMsgId = 0;
        var items = thread ? thread.querySelectorAll('[data-msg-id]') : [];
        if (items.length) lastMsgId = parseInt(items[items.length - 1].getAttribute('data-msg-id'), 10) || 0;

        // Enter to send
        var textarea = document.getElementById('msg-body-' + c.id);
        if (textarea) {
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    textarea.closest('form').dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }
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

    function renderAttachment(att) {
        if (att.status === 'pending' || att.status === 'processing') {
            return '<div class="flex items-center gap-2 py-2 text-xs opacity-60"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Descargando ' + esc(att.media_type) + '...</div>';
        }
        if (att.status === 'failed') {
            return '<div class="flex items-center gap-2 py-2 text-xs text-red-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Error al descargar</div>';
        }
        if (att.media_type === 'sticker' && att.url) {
            return '<img src="' + esc(att.thumbnail_url || att.url) + '" alt="Sticker" class="w-28 h-28 object-contain cursor-pointer hover:scale-105 transition mb-1" loading="lazy" onclick="openMediaModal(\'image\', \'' + esc(att.url) + '\', \'sticker.webp\')">';
        }
        if (att.media_type === 'image' && att.url) {
            return '<img src="' + esc(att.thumbnail_url || att.url) + '" alt="' + esc(att.file_name) + '" class="max-w-[250px] max-h-[250px] rounded-lg object-cover cursor-pointer hover:opacity-90 transition mb-1" loading="lazy" onclick="openMediaModal(\'image\', \'' + esc(att.url) + '\', \'' + esc(att.file_name) + '\')">';
        }
        if (att.media_type === 'video' && att.url) {
            return '<div class="relative cursor-pointer group mb-1" onclick="openMediaModal(\'video\', \'' + esc(att.url) + '\', \'' + esc(att.file_name) + '\')"><video preload="metadata" class="max-w-[280px] max-h-[250px] rounded-lg pointer-events-none"><source src="' + esc(att.url) + '" type="' + esc(att.mime_type) + '"></video><div class="absolute inset-0 flex items-center justify-center bg-black/20 rounded-lg group-hover:bg-black/30 transition"><svg class="w-12 h-12 text-white drop-shadow-lg" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div></div>';
        }
        if (att.media_type === 'audio' && att.url) {
            var dur = att.duration ? ' <span class="text-[10px] opacity-60">' + esc(att.duration) + '</span>' : '';
            return '<div class="flex items-center gap-2 mb-1 min-w-[200px]"><audio controls preload="metadata" class="h-10 w-full max-w-[250px]"><source src="' + esc(att.url) + '" type="' + esc(att.mime_type) + '"></audio>' + dur + '</div>';
        }
        if (att.media_type === 'document' && att.url) {
            var di = docIconHtml(att.file_name);
            return '<a href="' + esc(att.url) + '" target="_blank" class="flex items-center gap-3 p-2 rounded-lg bg-black/5 hover:bg-black/10 transition min-w-[180px] mb-1"><div class="w-10 h-10 rounded-lg ' + di.color + ' flex items-center justify-center shrink-0">' + di.svg + '</div><div class="min-w-0 flex-1"><p class="text-xs font-medium truncate">' + esc(att.file_name) + '</p><p class="text-[10px] opacity-60">' + esc(att.file_size) + '</p></div></a>';
        }
        return '';
    }

    function renderAttachments(attachments) {
        if (!attachments || !attachments.length) return '';
        var html = '';
        attachments.forEach(function(att) { html += renderAttachment(att); });
        return html;
    }

    function isMediaPlaceholder(body) {
        return ['[Image]', '[Audio]', '[Video]', '[Document]', '[Sticker]'].indexOf(body) !== -1;
    }

    function renderMessage(msg) {
        var html = '';
        var hasMedia = msg.attachments && msg.attachments.length > 0;
        var bodyHtml = '';

        if (hasMedia) {
            bodyHtml = renderAttachments(msg.attachments);
            if (msg.body && !isMediaPlaceholder(msg.body)) {
                bodyHtml += '<p class="mt-1 text-xs opacity-70">' + esc(msg.body) + '</p>';
            }
        } else {
            bodyHtml = esc(msg.body);
        }

        if (msg.type === 'internal_note') {
            html += '<div data-msg-id="' + msg.id + '" class="flex justify-center">';
            html += '<div class="max-w-md px-3 py-2 rounded-lg bg-yellow-50 border border-yellow-200 text-xs text-yellow-700">';
            html += '<span class="font-medium">' + esc(msg.user_name || 'System') + ':</span> ' + esc(msg.body);
            html += ' <span class="text-yellow-400 ml-2">' + esc(msg.time) + '</span></div></div>';
        } else if (msg.direction === 'inbound') {
            html += '<div data-msg-id="' + msg.id + '" class="flex justify-start">';
            html += '<div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-bl-sm bg-white shadow-sm text-sm text-gray-800">';
            html += bodyHtml + '<div class="text-[10px] text-gray-400 mt-1 text-right">' + esc(msg.time) + '</div></div></div>';
        } else {
            html += '<div data-msg-id="' + msg.id + '" class="flex justify-end">';
            html += '<div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-crea-primary text-white text-sm shadow-sm">';
            html += bodyHtml + '<div class="text-[10px] text-crea-secondary-light mt-1 text-right">' + esc(msg.user_name || 'Agent') + ' · ' + esc(msg.time) + '</div></div></div>';
        }
        return html;
    }

    function onAjaxFileSelect(convId) {
        var fileInput = document.getElementById('ajax-file-' + convId);
        var file = fileInput.files[0];
        if (!file) return;
        document.getElementById('ajax-file-name-' + convId).textContent = file.name;
        document.getElementById('ajax-file-size-' + convId).textContent = file.size >= 1048576 ? (file.size/1048576).toFixed(1) + ' MB' : Math.round(file.size/1024) + ' KB';
        document.getElementById('ajax-file-preview-' + convId).classList.remove('hidden');
        document.getElementById('msg-body-' + convId).removeAttribute('required');
    }

    function clearAjaxFile(convId) {
        document.getElementById('ajax-file-' + convId).value = '';
        document.getElementById('ajax-file-preview-' + convId).classList.add('hidden');
        document.getElementById('msg-body-' + convId).setAttribute('required', '');
    }

    function sendMessage(event, convId) {
        event.preventDefault();
        var textarea = document.getElementById('msg-body-' + convId);
        var fileInput = document.getElementById('ajax-file-' + convId);
        var body = textarea.value.trim();
        var hasFile = fileInput && fileInput.files.length > 0;
        if (!body && !hasFile) return;

        // Optimistic append
        if (hasFile) {
            var fname = fileInput.files[0].name;
            appendOptimistic(body ? body : 'Enviando ' + fname + '...', 'text');
        } else {
            appendOptimistic(body, 'text');
        }
        textarea.value = '';

        var fd = new FormData();
        fd.append('_token', csrfToken);
        if (body) fd.append('body', body);
        fd.append('type', 'text');
        if (hasFile) {
            fd.append('file', fileInput.files[0]);
            clearAjaxFile(convId);
        }

        fetch('/inbox/conversations/' + convId + '/messages', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: fd
        }).catch(function() {});
    }

    function sendNote(convId) {
        var textarea = document.getElementById('msg-body-' + convId);
        var body = textarea.value.trim();
        if (!body) return;

        appendOptimistic(body, 'internal_note');
        textarea.value = '';

        var fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('body', body);
        fd.append('type', 'internal_note');

        fetch('/inbox/conversations/' + convId + '/messages', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: fd
        }).catch(function() {});
    }

    function appendOptimistic(body, type) {
        var thread = document.getElementById('message-thread');
        if (!thread) return;
        var now = new Date();
        var time = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
        var div = document.createElement('div');
        div.setAttribute('data-optimistic', 'true');

        if (type === 'internal_note') {
            div.className = 'flex justify-center';
            div.innerHTML = '<div class="max-w-md px-3 py-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-xs text-yellow-700 dark:text-yellow-300"><span class="font-medium">Yo:</span> ' + esc(body) + ' <span class="text-yellow-400 ml-2">' + time + '</span></div>';
        } else {
            div.className = 'flex justify-end';
            div.innerHTML = '<div class="max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-crea-primary text-white text-sm shadow-sm">' + esc(body) + '<div class="text-[10px] text-crea-secondary-light mt-1 text-right">Yo · ' + time + '</div></div>';
        }
        thread.appendChild(div);
        thread.scrollTop = thread.scrollHeight;
    }

    var pendingAttachmentMsgs = {};
    var currentPollUrl = null;

    function hasPendingAttachments(msg) {
        if (!msg.attachments || !msg.attachments.length) return false;
        return msg.attachments.some(function(a) { return a.status === 'pending' || a.status === 'processing'; });
    }

    function pollPendingAttachments() {
        var ids = Object.keys(pendingAttachmentMsgs);
        if (!ids.length || !currentPollUrl) return;

        fetch(currentPollUrl + '?after_id=0', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.messages) return;
            data.messages.forEach(function(msg) {
                if (!pendingAttachmentMsgs[msg.id]) return;
                if (!hasPendingAttachments(msg)) {
                    delete pendingAttachmentMsgs[msg.id];
                    var el = document.querySelector('[data-msg-id="' + msg.id + '"]');
                    if (el) {
                        var bubble = el.querySelector('div > div') || el.querySelector('div');
                        if (bubble) {
                            var mediaHtml = renderAttachments(msg.attachments);
                            if (msg.body && !isMediaPlaceholder(msg.body)) {
                                mediaHtml += '<p class="mt-1 text-xs opacity-70">' + esc(msg.body) + '</p>';
                            }
                            var timeEl = bubble.querySelector('.text-\\[10px\\]');
                            var timeHtml = timeEl ? timeEl.outerHTML : '';
                            bubble.innerHTML = mediaHtml + timeHtml;
                        }
                    }
                }
            });
        })
        .catch(function() {});
    }

    setInterval(pollPendingAttachments, 3000);

    function setupPolling(pollUrl) {
        if (pollInterval) clearInterval(pollInterval);
        currentPollUrl = pollUrl;
        pollInterval = setInterval(function() {
            fetch(pollUrl + '?after_id=' + lastMsgId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.messages && data.messages.length) {
                    var thread = document.getElementById('message-thread');
                    data.messages.forEach(function(msg) {
                        if (document.querySelector('[data-msg-id="' + msg.id + '"]')) return;
                        // Play sound for inbound messages
                        if (msg.direction === 'inbound' && typeof playNotifSound === 'function') {
                            playNotifSound();
                        }
                        thread.insertAdjacentHTML('beforeend', renderMessage(msg));
                        if (msg.id > lastMsgId) lastMsgId = msg.id;
                        if (hasPendingAttachments(msg)) {
                            pendingAttachmentMsgs[msg.id] = true;
                        }
                    });
                    if (thread) thread.scrollTop = thread.scrollHeight;
                }
            })
            .catch(function() {});
        }, 5000);
    }

    function openInfoPanel(convId) {
        // Navigate to full show page for info panel
        window.location.href = '/inbox/conversations/' + convId;
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
        modal.offsetHeight; // force reflow
        modal.querySelector('.media-modal-backdrop').classList.add('opacity-100');
        modal.querySelector('.media-modal-content').classList.add('scale-100', 'opacity-100');
    }

    window.closeMediaModal = function() {
        var modal = document.getElementById('media-modal');
        var backdrop = modal.querySelector('.media-modal-backdrop');
        var content = modal.querySelector('.media-modal-content');

        backdrop.classList.remove('opacity-100');
        content.classList.remove('scale-100', 'opacity-100');

        // Stop any playing video
        var video = modal.querySelector('video');
        if (video) video.pause();

        setTimeout(function() { modal.classList.add('hidden'); }, 200);
    }

    function inboxApp() {
        return {
            orgId: {{ auth()->user()->organization_id ?? 'null' }},
            init() {
                if (!window.Echo || !this.orgId) return;
                window.Echo.private('organization.' + this.orgId)
                    .listen('ConversationCreated', function() { window.location.reload(); })
                    .listen('ConversationAssignedEvent', function() { window.location.reload(); })
                    .listen('ConversationClosedEvent', function() { window.location.reload(); });
            }
        }
    }
    </script>
    @endpush

    {{-- Media Modal --}}
    <div id="media-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center" onclick="if(event.target===this||event.target.classList.contains('media-modal-backdrop'))closeMediaModal()">
        <div class="media-modal-backdrop absolute inset-0 bg-black/80 opacity-0 transition-opacity duration-200"></div>
        <div class="media-modal-content relative z-10 max-w-[90vw] max-h-[90vh] flex flex-col items-center scale-95 opacity-0 transition-all duration-200">
            {{-- Top bar --}}
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
            {{-- Media content --}}
            <div id="media-modal-body" class="flex items-center justify-center"></div>
        </div>
    </div>

    {{-- Close modal on Escape key --}}
    <script>document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeMediaModal(); });</script>
</x-app-layout>
