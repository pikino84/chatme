<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('whatsapp-directo.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 truncate">
                    {{ $channel->name }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                    +{{ $session->connected_phone }}
                </p>
            </div>
        </div>
    </x-slot>

    <style>
        #chat-messages.select-mode .msg-check { display: inline-block; }
        #chat-messages.select-mode .msg-row { cursor: pointer; }
        .msg-check { display: none; }
    </style>

    <div class="flex overflow-hidden sm:-m-6" style="height: calc(100vh - 64px);" id="wa-directo-app"
        data-channel-id="{{ $channel->id }}"
        data-conversations-url="{{ route('whatsapp-directo.conversations', $channel) }}"
        data-forward-targets-url="{{ route('whatsapp-directo.forward-targets', $channel) }}"
        data-conversation-base="/whatsapp-directo/{{ $channel->id }}/conversations"
        data-deals-base="{{ url('/deals') }}">

        {{-- Left: Conversation list --}}
        <div class="w-full lg:w-80 border-r border-gray-200 dark:border-gray-700 flex flex-col min-h-0 bg-white dark:bg-gray-800 shrink-0" id="conv-pane">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <input type="text" id="conv-search" placeholder="Buscar..." autocomplete="off"
                    class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700" id="conv-list">
                <div class="p-6 text-center text-sm text-gray-500">Cargando...</div>
            </div>
        </div>

        {{-- Right: Chat panel --}}
        <div class="hidden lg:flex flex-1 flex-col min-h-0 min-w-0 bg-gray-50 dark:bg-gray-900" id="chat-pane">
            <div id="chat-empty" class="flex-1 flex items-center justify-center text-gray-400 dark:text-gray-500">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <p class="text-sm">Selecciona una conversación</p>
                </div>
            </div>

            <div id="chat-active" class="hidden flex-1 flex flex-col min-h-0 relative">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center gap-2">
                    <button type="button" id="chat-close-mobile" class="lg:hidden text-gray-500 hover:text-gray-700 p-1 -ml-1 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <h3 id="chat-name" class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate"></h3>
                        <p id="chat-phone" class="text-xs text-gray-500 dark:text-gray-400"></p>
                    </div>
                    <button type="button" id="btn-select-mode" title="Seleccionar mensajes"
                        class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-xs font-medium shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        <span class="hidden sm:inline">Seleccionar</span>
                    </button>
                    <button type="button" id="btn-info" title="Información del contacto"
                        class="flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                </div>

                <div id="chat-messages" class="flex-1 min-h-0 overflow-y-auto px-4 py-4 space-y-2"></div>

                {{-- Select-mode action bar --}}
                <div id="select-bar" class="hidden border-t border-gray-200 dark:border-gray-700 bg-green-50 dark:bg-green-900/20 px-4 py-2.5 flex items-center justify-between gap-2">
                    <span class="text-sm text-gray-700 dark:text-gray-200"><span id="select-count">0</span> seleccionados</span>
                    <div class="flex items-center gap-2">
                        <button type="button" id="btn-forward" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white rounded-lg text-sm font-medium flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            Reenviar
                        </button>
                        <button type="button" id="btn-delete-msgs" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 disabled:bg-gray-300 text-white rounded-lg text-sm font-medium flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Borrar
                        </button>
                        <button type="button" id="btn-cancel-select" class="px-2.5 py-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 text-sm">Cancelar</button>
                    </div>
                </div>

                <form id="chat-form" class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 flex gap-2 items-center">
                    <input type="file" id="chat-file" class="hidden" accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
                    <button type="button" id="chat-attach" title="Adjuntar archivo"
                        class="shrink-0 p-2 text-gray-500 hover:text-green-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full disabled:opacity-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </button>
                    <input type="text" id="chat-input" placeholder="Escribe un mensaje..." autocomplete="off"
                        class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
                    <button type="submit" id="chat-send"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white text-sm font-medium rounded">
                        Enviar
                    </button>
                </form>

                {{-- Info / lead drawer --}}
                <aside id="info-drawer" class="hidden absolute inset-y-0 right-0 w-full sm:w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 shadow-xl z-20 flex-col">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Información</h3>
                        <button type="button" id="btn-close-info" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div id="info-body" class="flex-1 overflow-y-auto p-4 space-y-5 text-sm">
                        <div class="text-center text-gray-400 text-xs py-6">Cargando...</div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    {{-- Forward modal --}}
    <div id="forward-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md flex flex-col max-h-[85vh]">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Reenviar a</h3>
                <button type="button" id="btn-close-forward" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-4 space-y-3 overflow-y-auto">
                <input type="text" id="fwd-search" placeholder="Buscar conversación..." autocomplete="off"
                    class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
                <div id="fwd-list" class="max-h-52 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700 border border-gray-100 dark:border-gray-700 rounded">
                    <div class="p-4 text-center text-xs text-gray-400">Cargando...</div>
                </div>
                <div class="flex gap-2">
                    <input type="text" id="fwd-phone" placeholder="O escribe un número (10-15 dígitos)" inputmode="numeric" autocomplete="off"
                        class="flex-1 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
                    <button type="button" id="fwd-add-phone" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded text-sm hover:bg-gray-200">Agregar</button>
                </div>
                <div id="fwd-chips" class="flex flex-wrap gap-1.5"></div>
            </div>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-2">
                <button type="button" id="btn-cancel-forward" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">Cancelar</button>
                <button type="button" id="btn-do-forward" class="px-4 py-1.5 bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white rounded text-sm font-medium">Reenviar</button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function() {
            const app = document.getElementById('wa-directo-app');
            const CHANNEL_ID = app.dataset.channelId;
            const CONV_LIST_URL = app.dataset.conversationsUrl;
            const FWD_TARGETS_URL = app.dataset.forwardTargetsUrl;
            const CONV_BASE = app.dataset.conversationBase;
            const CSRF = @json(csrf_token());

            const convListEl = document.getElementById('conv-list');
            const convSearch = document.getElementById('conv-search');
            const chatEmpty = document.getElementById('chat-empty');
            const chatActive = document.getElementById('chat-active');
            const chatName = document.getElementById('chat-name');
            const chatPhone = document.getElementById('chat-phone');
            const chatMessages = document.getElementById('chat-messages');
            const chatForm = document.getElementById('chat-form');
            const chatInput = document.getElementById('chat-input');
            const chatSend = document.getElementById('chat-send');
            const chatCloseMobile = document.getElementById('chat-close-mobile');
            const convPane = document.getElementById('conv-pane');
            const chatPane = document.getElementById('chat-pane');

            const btnSelectMode = document.getElementById('btn-select-mode');
            const btnInfo = document.getElementById('btn-info');
            const selectBar = document.getElementById('select-bar');
            const selectCount = document.getElementById('select-count');
            const btnForward = document.getElementById('btn-forward');
            const btnDeleteMsgs = document.getElementById('btn-delete-msgs');
            const btnCancelSelect = document.getElementById('btn-cancel-select');

            const infoDrawer = document.getElementById('info-drawer');
            const infoBody = document.getElementById('info-body');
            const btnCloseInfo = document.getElementById('btn-close-info');

            const forwardModal = document.getElementById('forward-modal');
            const fwdSearch = document.getElementById('fwd-search');
            const fwdList = document.getElementById('fwd-list');
            const fwdPhone = document.getElementById('fwd-phone');
            const fwdAddPhone = document.getElementById('fwd-add-phone');
            const fwdChips = document.getElementById('fwd-chips');
            const btnCloseForward = document.getElementById('btn-close-forward');
            const btnCancelForward = document.getElementById('btn-cancel-forward');
            const btnDoForward = document.getElementById('btn-do-forward');

            let activeConvId = null;
            let conversations = [];
            let messageIds = new Set();
            let selectMode = false;
            let selectedMsgIds = new Set();
            let fwdTargets = [];          // [{id, contact_name, contact_identifier}]
            let fwdSelectedConvIds = new Set();
            let fwdSelectedPhones = new Set();

            function escapeHtml(s) {
                return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
            }
            function fmtTime(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                const now = new Date();
                if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                return d.toLocaleDateString([], {day: '2-digit', month: 'short'});
            }
            async function postJson(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify(body || {}),
                });
                let data = {};
                try { data = await res.json(); } catch (e) {}
                return { ok: res.ok, data };
            }

            /* ---------- Conversation list ---------- */
            function renderConvList() {
                const q = convSearch.value.trim().toLowerCase();
                const filtered = q
                    ? conversations.filter(c => (c.contact_name || '').toLowerCase().includes(q) || (c.contact_identifier || '').toLowerCase().includes(q))
                    : conversations;
                if (filtered.length === 0) {
                    convListEl.innerHTML = '<div class="p-6 text-center text-sm text-gray-500">Sin conversaciones</div>';
                    return;
                }
                convListEl.innerHTML = filtered.map(c => {
                    const active = c.id === activeConvId;
                    const unreadBadge = c.unread_count > 0
                        ? `<span class="inline-flex items-center justify-center w-5 h-5 text-xs bg-green-500 text-white rounded-full">${c.unread_count}</span>` : '';
                    const preview = c.last_message_preview
                        ? (c.last_message_direction === 'outbound' ? '✓ ' : '') + escapeHtml(c.last_message_preview.slice(0, 60)) : 'Sin mensajes';
                    const closed = c.status === 'closed' ? '<span class="text-[10px] text-gray-400">(cerrada)</span> ' : '';
                    return `
                        <div class="conv-item px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/30 ${active ? 'bg-green-50 dark:bg-green-900/20 border-l-2 border-green-500' : ''}" data-id="${c.id}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate ${c.unread_count > 0 ? 'font-bold' : ''}">${closed}${escapeHtml(c.contact_name || c.contact_identifier)}</span>
                                <span class="text-xs text-gray-400 shrink-0">${fmtTime(c.last_message_at)}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 mt-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400 truncate">${preview}</span>
                                ${unreadBadge}
                            </div>
                        </div>`;
                }).join('');
                convListEl.querySelectorAll('.conv-item').forEach(el => el.addEventListener('click', () => loadConversation(parseInt(el.dataset.id, 10))));
            }
            async function loadConversations() {
                try {
                    const res = await fetch(CONV_LIST_URL, { headers: {'Accept': 'application/json'} });
                    conversations = (await res.json()).conversations || [];
                    renderConvList();
                } catch (err) { console.error('loadConversations failed', err); }
            }

            /* ---------- Messages ---------- */
            // Palomitas de estado (solo salientes): enviado / entregado / leído.
            // Estado vía metadata.wa_ack_status (Evolution messages.update):
            // SERVER_ACK=enviado, DELIVERY_ACK=entregado, READ/PLAYED=leído.
            function ackInner(m) {
                if (m.direction !== 'outbound') return '';
                const meta = m.metadata || {};
                if (meta.send_failed) return '<span title="No enviado" class="inline-flex items-center font-bold" style="color:#ef4444">!</span>';
                const SINGLE = '<svg width="16" height="15" viewBox="0 0 16 15" fill="currentColor"><path d="M10.91 3.316l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.515.006l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185a.32.32 0 0 0 .484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"/></svg>';
                const DOUBLE = '<svg width="16" height="15" viewBox="0 0 16 15" fill="currentColor"><path d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.879a.32.32 0 0 1-.484.033l-.358-.325a.319.319 0 0 0-.484.032l-.378.483a.418.418 0 0 0 .036.541l1.32 1.266a.32.32 0 0 0 .484-.034l6.272-8.048a.366.366 0 0 0-.063-.51zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.515.006l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185a.32.32 0 0 0 .484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"/></svg>';
                const st = String(meta.wa_ack_status == null ? '' : meta.wa_ack_status).toUpperCase();
                if (st === 'READ' || st === 'PLAYED' || st === '4' || st === '5')
                    return '<span title="Leído" class="inline-flex items-center" style="color:#53bdeb">' + DOUBLE + '</span>';
                if (st === 'DELIVERY_ACK' || st === 'DELIVERED' || st === '3')
                    return '<span title="Entregado" class="inline-flex items-center text-gray-400">' + DOUBLE + '</span>';
                if (st === 'SERVER_ACK' || st === 'SENT' || st === '2')
                    return '<span title="Enviado" class="inline-flex items-center text-gray-400">' + SINGLE + '</span>';
                if (meta.pending)
                    return '<span title="Enviando…" class="inline-flex items-center text-gray-400"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>';
                return '<span title="Enviado" class="inline-flex items-center text-gray-400">' + SINGLE + '</span>';
            }
            function updateAck(m) {
                if (m.direction !== 'outbound') return;
                const el = chatMessages.querySelector(`[data-id="${m.id}"] .ack-indicator`);
                if (el) el.innerHTML = ackInner(m);
            }
            // --- Adjuntos multimedia (Phase 22.4) -------------------------------
            function renderAttachment(att) {
                const u = att.url || '';
                if (att.status === 'pending' || att.status === 'processing') {
                    return '<div class="flex items-center gap-2 py-1 text-xs opacity-60"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Descargando ' + escapeHtml(att.media_type || 'archivo') + '…</div>';
                }
                if (att.status === 'failed') {
                    return '<div class="flex items-center gap-2 py-1 text-xs text-red-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>No se pudo descargar</div>';
                }
                if (!u) return '';
                if (att.media_type === 'sticker') {
                    return '<img src="' + escapeHtml(att.thumbnail_url || u) + '" alt="sticker" class="w-28 h-28 object-contain mb-1" loading="lazy">';
                }
                if (att.media_type === 'image') {
                    return '<a href="' + escapeHtml(u) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(att.thumbnail_url || u) + '" alt="' + escapeHtml(att.file_name || '') + '" class="max-w-[250px] max-h-[250px] rounded-lg object-cover cursor-pointer hover:opacity-90 transition mb-1" loading="lazy"></a>';
                }
                if (att.media_type === 'video') {
                    return '<video controls preload="metadata" class="max-w-[280px] max-h-[250px] rounded-lg mb-1"><source src="' + escapeHtml(u) + '" type="' + escapeHtml(att.mime_type || '') + '"></video>';
                }
                if (att.media_type === 'audio') {
                    return '<audio controls preload="metadata" class="h-10 max-w-[250px] mb-1"><source src="' + escapeHtml(u) + '" type="' + escapeHtml(att.mime_type || '') + '"></audio>';
                }
                return '<a href="' + escapeHtml(u) + '" target="_blank" rel="noopener" class="flex items-center gap-2 p-2 rounded-lg bg-black/5 hover:bg-black/10 transition min-w-[180px] mb-1"><svg class="w-8 h-8 shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg><div class="min-w-0 flex-1"><p class="text-xs font-medium truncate">' + escapeHtml(att.file_name || 'archivo') + '</p><p class="text-[10px] opacity-60">' + escapeHtml(att.file_size || '') + '</p></div></a>';
            }
            function renderAttachments(atts) {
                if (!atts || !atts.length) return '';
                return atts.map(renderAttachment).join('');
            }
            function isMediaPlaceholder(body) {
                return typeof body === 'string' && /^\[.+\]$/.test(body.trim());
            }
            const mediaState = {};
            function attSig(m) {
                return (m.attachments || []).map(a => a.status + ':' + (a.url ? '1' : '0')).join(',');
            }
            function updateMedia(m) {
                if (!m.attachments || !m.attachments.length) return;
                const sig = attSig(m);
                if (mediaState[m.id] === sig) return;
                mediaState[m.id] = sig;
                const box = chatMessages.querySelector(`[data-id="${m.id}"] .media-box`);
                if (box) box.innerHTML = renderAttachments(m.attachments);
            }
            function renderMessage(m) {
                if (messageIds.has(m.id)) { updateAck(m); updateMedia(m); return; }
                messageIds.add(m.id);
                const isOut = m.direction === 'outbound';
                const hasMedia = m.attachments && m.attachments.length > 0;
                if (hasMedia) mediaState[m.id] = attSig(m);
                const mediaHtml = hasMedia ? renderAttachments(m.attachments) : '';
                const showBody = m.body && !(hasMedia && isMediaPlaceholder(m.body));
                const bodyHtml = showBody ? `<p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap break-words">${escapeHtml(m.body)}</p>` : '';
                const fromPhone = (isOut && m.metadata && m.metadata.from_phone)
                    ? '<span title="Enviado desde el teléfono" class="inline-flex items-center align-middle opacity-60"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17h2m-5 4h8a1 1 0 001-1V4a1 1 0 00-1-1H8a1 1 0 00-1 1v16a1 1 0 001 1z"/></svg></span>'
                    : '';
                const fwd = m.metadata && m.metadata.forwarded
                    ? '<div class="text-[10px] italic opacity-60 mb-0.5 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>Reenviado</div>' : '';
                const row = document.createElement('div');
                row.className = `msg-row flex items-center gap-2 ${isOut ? 'justify-end' : 'justify-start'}`;
                row.dataset.id = m.id;
                row.innerHTML = `
                    <input type="checkbox" class="msg-check w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500 shrink-0" value="${m.id}">
                    <div class="${isOut ? 'bg-green-100 dark:bg-green-900/40' : 'bg-white dark:bg-gray-800'} max-w-[75%] px-3 py-2 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        ${fwd}
                        <div class="media-box">${mediaHtml}</div>
                        ${bodyHtml}
                        <p class="text-[10px] text-gray-400 mt-1 text-right flex items-center justify-end gap-1">${fromPhone}<span>${fmtTime(m.created_at)}</span><span class="ack-indicator inline-flex items-center">${ackInner(m)}</span></p>
                    </div>`;
                const cb = row.querySelector('.msg-check');
                cb.addEventListener('change', () => { toggleSelected(m.id, cb.checked); });
                row.addEventListener('click', (e) => {
                    if (!selectMode) return;
                    if (e.target === cb) return;
                    cb.checked = !cb.checked;
                    toggleSelected(m.id, cb.checked);
                });
                chatMessages.appendChild(row);
            }
            function clearMessages() { chatMessages.innerHTML = ''; messageIds = new Set(); }

            async function loadConversation(convId) {
                activeConvId = convId;
                exitSelectMode();
                closeInfoDrawer();
                clearMessages();
                chatEmpty.classList.add('hidden');
                chatActive.classList.remove('hidden');
                chatPane.classList.remove('hidden');
                chatPane.classList.add('flex');
                if (window.innerWidth < 1024) convPane.classList.add('hidden');
                renderConvList();
                try {
                    const res = await fetch(`${CONV_BASE}/${convId}`, { headers: {'Accept': 'application/json'} });
                    const data = await res.json();
                    chatName.textContent = data.conversation.contact_name || data.conversation.contact_identifier;
                    chatPhone.textContent = '+' + data.conversation.contact_identifier;
                    (data.messages || []).forEach(renderMessage);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    fetch(`${CONV_BASE}/${convId}/read`, { method: 'POST', headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'} });
                } catch (err) { console.error('loadConversation failed', err); }
            }
            async function refreshCurrentConversation() {
                if (!activeConvId) return;
                try {
                    const res = await fetch(`${CONV_BASE}/${activeConvId}`, { headers: {'Accept':'application/json'} });
                    const data = await res.json();
                    (data.messages || []).forEach(renderMessage);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                } catch (err) { /* silent */ }
            }
            async function reloadCurrentConversation() {
                if (!activeConvId) return;
                clearMessages();
                try {
                    const res = await fetch(`${CONV_BASE}/${activeConvId}`, { headers: {'Accept':'application/json'} });
                    const data = await res.json();
                    (data.messages || []).forEach(renderMessage);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                } catch (err) { /* silent */ }
            }

            chatCloseMobile.addEventListener('click', () => {
                chatPane.classList.add('hidden');
                chatPane.classList.remove('flex');
                convPane.classList.remove('hidden');
                activeConvId = null;
                renderConvList();
            });

            /* ---------- Send ---------- */
            chatForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!activeConvId) return;
                const body = chatInput.value.trim();
                if (!body || chatSend.disabled) return;
                chatSend.disabled = true;
                chatInput.value = '';
                try {
                    const res = await fetch(`${CONV_BASE}/${activeConvId}/send`, {
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                        body: JSON.stringify({body}),
                    });
                    if (!res.ok) throw new Error('send failed');
                    await refreshCurrentConversation();
                } catch (err) {
                    console.error('send failed', err);
                    chatInput.value = body;
                    alert('No se pudo enviar. Intenta de nuevo.');
                } finally {
                    chatSend.disabled = false;
                    chatInput.focus();
                }
            });

            /* ---------- Adjuntar archivo (multimedia saliente) ---------- */
            const chatFile = document.getElementById('chat-file');
            const chatAttach = document.getElementById('chat-attach');
            chatAttach.addEventListener('click', () => { if (activeConvId) chatFile.click(); });
            chatFile.addEventListener('change', async () => {
                if (!activeConvId || !chatFile.files.length) return;
                const file = chatFile.files[0];
                if (file.size > 16 * 1024 * 1024) { alert('El archivo supera el límite de 16 MB.'); chatFile.value = ''; return; }
                const fd = new FormData();
                fd.append('file', file);
                const caption = chatInput.value.trim();
                if (caption) fd.append('caption', caption);
                chatFile.value = '';
                chatInput.value = '';
                chatSend.disabled = true;
                chatAttach.disabled = true;
                try {
                    const res = await fetch(`${CONV_BASE}/${activeConvId}/send-media`, {
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
                        body: fd,
                    });
                    if (!res.ok) throw new Error('upload failed');
                    const data = await res.json();
                    if (data.failed) alert('El archivo se subió pero WhatsApp rechazó el envío.');
                    await refreshCurrentConversation();
                } catch (err) {
                    console.error('media send failed', err);
                    alert('No se pudo enviar el archivo. Intenta de nuevo.');
                } finally {
                    chatSend.disabled = false;
                    chatAttach.disabled = false;
                    chatInput.focus();
                }
            });

            /* ---------- Select mode ---------- */
            function enterSelectMode() {
                selectMode = true;
                selectedMsgIds = new Set();
                chatMessages.classList.add('select-mode');
                selectBar.classList.remove('hidden');
                updateSelectCount();
                closeInfoDrawer();
            }
            function exitSelectMode() {
                selectMode = false;
                selectedMsgIds = new Set();
                chatMessages.classList.remove('select-mode');
                chatMessages.querySelectorAll('.msg-check').forEach(cb => cb.checked = false);
                selectBar.classList.add('hidden');
            }
            function toggleSelected(id, on) {
                if (on) selectedMsgIds.add(id); else selectedMsgIds.delete(id);
                updateSelectCount();
            }
            function updateSelectCount() {
                const n = selectedMsgIds.size;
                selectCount.textContent = n;
                btnForward.disabled = n === 0;
                btnDeleteMsgs.disabled = n === 0;
            }
            btnSelectMode.addEventListener('click', () => { selectMode ? exitSelectMode() : enterSelectMode(); });
            btnCancelSelect.addEventListener('click', exitSelectMode);

            btnDeleteMsgs.addEventListener('click', async () => {
                const ids = Array.from(selectedMsgIds);
                if (!ids.length) return;
                if (!confirm(`¿Borrar ${ids.length} mensaje(s)? Solo se eliminan del historial local; el contacto los seguirá viendo.`)) return;
                btnDeleteMsgs.disabled = true;
                const { ok, data } = await postJson(`${CONV_BASE}/${activeConvId}/messages/delete`, { message_ids: ids });
                if (!ok) { alert(data.error || 'No se pudieron borrar los mensajes.'); btnDeleteMsgs.disabled = false; return; }
                exitSelectMode();
                await reloadCurrentConversation();
                loadConversations();
            });

            /* ---------- Forward modal ---------- */
            btnForward.addEventListener('click', () => {
                if (!selectedMsgIds.size) return;
                fwdSelectedConvIds = new Set();
                fwdSelectedPhones = new Set();
                fwdSearch.value = '';
                fwdPhone.value = '';
                renderFwdChips();
                forwardModal.classList.remove('hidden');
                loadFwdTargets();
            });
            function closeForwardModal() { forwardModal.classList.add('hidden'); }
            btnCloseForward.addEventListener('click', closeForwardModal);
            btnCancelForward.addEventListener('click', closeForwardModal);
            forwardModal.addEventListener('click', (e) => { if (e.target === forwardModal) closeForwardModal(); });

            async function loadFwdTargets() {
                const q = fwdSearch.value.trim();
                try {
                    const res = await fetch(FWD_TARGETS_URL + (q ? ('?q=' + encodeURIComponent(q)) : ''), { headers: {'Accept':'application/json'} });
                    fwdTargets = (await res.json()).targets || [];
                } catch (e) { fwdTargets = []; }
                renderFwdList();
            }
            function renderFwdList() {
                if (!fwdTargets.length) { fwdList.innerHTML = '<div class="p-4 text-center text-xs text-gray-400">Sin conversaciones</div>'; return; }
                fwdList.innerHTML = fwdTargets.map(t => {
                    const checked = fwdSelectedConvIds.has(t.id) ? 'checked' : '';
                    return `<label class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <input type="checkbox" class="fwd-conv w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500" value="${t.id}" ${checked}>
                        <span class="text-sm text-gray-800 dark:text-gray-100 truncate">${escapeHtml(t.contact_name || t.contact_identifier)}</span>
                        <span class="text-xs text-gray-400 ml-auto shrink-0">+${escapeHtml(t.contact_identifier)}</span>
                    </label>`;
                }).join('');
                fwdList.querySelectorAll('.fwd-conv').forEach(cb => cb.addEventListener('change', () => {
                    const id = parseInt(cb.value, 10);
                    if (cb.checked) fwdSelectedConvIds.add(id); else fwdSelectedConvIds.delete(id);
                    renderFwdChips();
                }));
            }
            let fwdSearchT = null;
            fwdSearch.addEventListener('input', () => { clearTimeout(fwdSearchT); fwdSearchT = setTimeout(loadFwdTargets, 300); });

            fwdAddPhone.addEventListener('click', () => {
                const raw = (fwdPhone.value || '').replace(/\D/g, '');
                if (raw.length < 10 || raw.length > 15) { alert('Número inválido (10 a 15 dígitos).'); return; }
                fwdSelectedPhones.add(raw);
                fwdPhone.value = '';
                renderFwdChips();
            });
            fwdPhone.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); fwdAddPhone.click(); } });

            function renderFwdChips() {
                const chips = [];
                fwdSelectedConvIds.forEach(id => {
                    const t = fwdTargets.find(x => x.id === id);
                    const label = t ? (t.contact_name || t.contact_identifier) : ('Conv #' + id);
                    chips.push(`<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 text-xs">${escapeHtml(label)}<button type="button" data-conv="${id}" class="fwd-chip-x">&times;</button></span>`);
                });
                fwdSelectedPhones.forEach(p => {
                    chips.push(`<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs">+${escapeHtml(p)}<button type="button" data-phone="${escapeHtml(p)}" class="fwd-chip-x">&times;</button></span>`);
                });
                fwdChips.innerHTML = chips.join('');
                fwdChips.querySelectorAll('.fwd-chip-x').forEach(b => b.addEventListener('click', () => {
                    if (b.dataset.conv) fwdSelectedConvIds.delete(parseInt(b.dataset.conv, 10));
                    if (b.dataset.phone) fwdSelectedPhones.delete(b.dataset.phone);
                    renderFwdList(); renderFwdChips();
                }));
                btnDoForward.disabled = (fwdSelectedConvIds.size + fwdSelectedPhones.size) === 0;
            }

            btnDoForward.addEventListener('click', async () => {
                const ids = Array.from(selectedMsgIds);
                if (!ids.length) { closeForwardModal(); return; }
                if (!fwdSelectedConvIds.size && !fwdSelectedPhones.size) { alert('Selecciona al menos un destino.'); return; }
                btnDoForward.disabled = true;
                const { ok, data } = await postJson(`${CONV_BASE}/${activeConvId}/messages/forward`, {
                    message_ids: ids,
                    conversation_ids: Array.from(fwdSelectedConvIds),
                    phones: Array.from(fwdSelectedPhones),
                });
                btnDoForward.disabled = false;
                if (!ok) { alert(data.error || 'No se pudieron reenviar los mensajes.'); return; }
                closeForwardModal();
                exitSelectMode();
                loadConversations();
                alert(`Reenviado a ${data.targets} destino(s).`);
            });

            /* ---------- Info / lead drawer ---------- */
            function openInfoDrawer() {
                exitSelectMode();
                infoDrawer.classList.remove('hidden');
                infoDrawer.classList.add('flex');
                infoBody.innerHTML = '<div class="text-center text-gray-400 text-xs py-6">Cargando...</div>';
                loadInfo();
            }
            function closeInfoDrawer() {
                infoDrawer.classList.add('hidden');
                infoDrawer.classList.remove('flex');
            }
            btnInfo.addEventListener('click', () => {
                if (!activeConvId) return;
                infoDrawer.classList.contains('hidden') ? openInfoDrawer() : closeInfoDrawer();
            });
            btnCloseInfo.addEventListener('click', closeInfoDrawer);

            async function loadInfo() {
                try {
                    const res = await fetch(`${CONV_BASE}/${activeConvId}/info`, { headers: {'Accept':'application/json'} });
                    const data = await res.json();
                    renderInfo(data);
                } catch (e) {
                    infoBody.innerHTML = '<div class="text-center text-red-500 text-xs py-6">Error al cargar.</div>';
                }
            }
            function renderInfo(d) {
                const c = d.conversation, can = d.can || {};
                let html = '';
                // Contact
                html += `<div>
                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Contacto</h4>
                    <dl class="space-y-1 text-xs">
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Nombre</dt><dd class="text-gray-900 dark:text-gray-200">${escapeHtml(c.contact_name || '-')}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Teléfono</dt><dd class="text-gray-900 dark:text-gray-200 font-mono">+${escapeHtml(c.contact_identifier)}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Estado</dt><dd>${c.status === 'closed' ? '<span class="px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Cerrada</span>' : '<span class="px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">Abierta</span>'}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Agente</dt><dd class="text-gray-900 dark:text-gray-200">${escapeHtml(c.assigned_user_name || 'Sin asignar')}</dd></div>
                    </dl>
                </div>`;
                // Status actions
                let actions = '';
                if (c.status !== 'closed' && can.close) actions += `<button type="button" data-action="close" class="info-act w-full text-xs px-3 py-1.5 rounded bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300">Cerrar conversación</button>`;
                if (c.status === 'closed' && can.reopen) actions += `<button type="button" data-action="reopen" class="info-act w-full text-xs px-3 py-1.5 rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300">Reabrir conversación</button>`;
                if (can.assign && (d.agents || []).length) {
                    const opts = d.agents.map(a => `<option value="${a.id}" ${a.id === c.assigned_user_id ? 'selected' : ''}>${escapeHtml(a.name)}</option>`).join('');
                    actions += `<div class="flex gap-1"><select id="info-assign-select" class="flex-1 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">${opts}</select><button type="button" data-action="assign" class="info-act text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300">Asignar</button></div>`;
                }
                if (actions) html += `<div><h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Acciones</h4><div class="space-y-2">${actions}</div></div>`;
                // Linked deal / convert
                if ((d.deals || []).length) {
                    html += `<div><h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Negocio vinculado</h4>` +
                        d.deals.map(dl => `<a href="${dl.url}" class="block text-xs text-indigo-600 dark:text-indigo-400 hover:underline">${escapeHtml(dl.contact_name)} — $${Number(dl.value).toLocaleString()}</a>`).join('') + `</div>`;
                } else if (can.create_deal) {
                    html += `<div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Pasar lead a negocio</h4>
                        <div class="space-y-2">
                            <div><label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Nombre del contacto</label>
                                <input type="text" id="deal-name" value="${escapeHtml(c.contact_name || '')}" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"></div>
                            <div><label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Teléfono</label>
                                <input type="text" value="+${escapeHtml(c.contact_identifier)}" disabled class="w-full text-xs rounded border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-400"></div>
                            <div><label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Fecha estimada de cierre</label>
                                <input type="date" id="deal-close" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"></div>
                            <button type="button" data-action="create-deal" class="info-act w-full text-xs px-3 py-1.5 rounded bg-green-600 text-white hover:bg-green-700 font-medium">Crear negocio</button>
                        </div>
                    </div>`;
                }
                infoBody.innerHTML = html;
                infoBody.querySelectorAll('.info-act').forEach(b => b.addEventListener('click', () => handleInfoAction(b.dataset.action, b)));
            }
            async function handleInfoAction(action, btn) {
                if (action === 'close' || action === 'reopen') {
                    btn.disabled = true;
                    const { ok, data } = await postJson(`${CONV_BASE}/${activeConvId}/${action}`, {});
                    if (!ok) { alert(data.error || 'Error.'); btn.disabled = false; return; }
                    loadInfo(); loadConversations();
                } else if (action === 'assign') {
                    const sel = document.getElementById('info-assign-select');
                    if (!sel) return;
                    btn.disabled = true;
                    const { ok, data } = await postJson(`${CONV_BASE}/${activeConvId}/assign`, { assigned_user_id: parseInt(sel.value, 10) });
                    if (!ok) { alert(data.error || 'Error.'); btn.disabled = false; return; }
                    loadInfo();
                } else if (action === 'create-deal') {
                    const name = (document.getElementById('deal-name').value || '').trim();
                    if (!name) { alert('Escribe el nombre del contacto.'); return; }
                    const closeDate = document.getElementById('deal-close').value || null;
                    btn.disabled = true;
                    const { ok, data } = await postJson(`${CONV_BASE}/${activeConvId}/convert-to-deal`, { contact_name: name, expected_close_date: closeDate });
                    if (!ok) { alert(data.error || 'No se pudo crear el negocio.'); btn.disabled = false; return; }
                    loadInfo();
                    if (data.deal && data.deal.url && confirm((data.message || 'Negocio creado.') + '\n\n¿Abrir el negocio?')) {
                        window.open(data.deal.url, '_blank');
                    }
                }
            }

            convSearch.addEventListener('input', renderConvList);

            // Polling
            loadConversations();
            setInterval(async () => {
                // Si hay una conversación abierta, tráete sus mensajes nuevos y vuelve a
                // marcarla como leída ANTES de refrescar la lista: así su badge no crece
                // con los mensajes que estás viendo en vivo.
                if (activeConvId && !selectMode) {
                    try {
                        const res = await fetch(`${CONV_BASE}/${activeConvId}`, { headers: {'Accept':'application/json'} });
                        const data = await res.json();
                        (data.messages || []).forEach(renderMessage);
                        fetch(`${CONV_BASE}/${activeConvId}/read`, { method: 'POST', headers: {'X-CSRF-TOKEN': CSRF, 'Accept':'application/json'} });
                    } catch (err) { /* silent */ }
                }
                await loadConversations();
            }, 5000);
        })();
    </script>
    @endpush
</x-app-layout>
