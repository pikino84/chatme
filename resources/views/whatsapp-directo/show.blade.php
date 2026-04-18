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

    <div class="flex overflow-hidden sm:-m-6" style="height: calc(100vh - 64px);" id="wa-directo-app"
        data-channel-id="{{ $channel->id }}"
        data-conversations-url="{{ route('whatsapp-directo.conversations', $channel) }}"
        data-conversation-base="/whatsapp-directo/{{ $channel->id }}/conversations">

        {{-- Left: Conversation list --}}
        <div class="w-full lg:w-80 border-r border-gray-200 dark:border-gray-700 flex flex-col bg-white dark:bg-gray-800 shrink-0" id="conv-pane">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <input type="text" id="conv-search" placeholder="Buscar..." autocomplete="off"
                    class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700" id="conv-list">
                <div class="p-6 text-center text-sm text-gray-500">Cargando...</div>
            </div>
        </div>

        {{-- Right: Chat panel --}}
        <div class="hidden lg:flex flex-1 flex-col bg-gray-50 dark:bg-gray-900" id="chat-pane">
            <div id="chat-empty" class="flex-1 flex items-center justify-center text-gray-400 dark:text-gray-500">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <p class="text-sm">Selecciona una conversación</p>
                </div>
            </div>

            <div id="chat-active" class="hidden flex-1 flex flex-col">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center justify-between">
                    <div class="min-w-0">
                        <h3 id="chat-name" class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate"></h3>
                        <p id="chat-phone" class="text-xs text-gray-500 dark:text-gray-400"></p>
                    </div>
                    <button type="button" id="chat-close-mobile" class="lg:hidden text-gray-500 hover:text-gray-700 p-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div id="chat-messages" class="flex-1 overflow-y-auto px-4 py-4 space-y-2">
                </div>

                <form id="chat-form" class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 flex gap-2">
                    <input type="text" id="chat-input" placeholder="Escribe un mensaje..." autocomplete="off"
                        class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
                    <button type="submit" id="chat-send"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white text-sm font-medium rounded">
                        Enviar
                    </button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function() {
            const app = document.getElementById('wa-directo-app');
            const CHANNEL_ID = app.dataset.channelId;
            const CONV_LIST_URL = app.dataset.conversationsUrl;
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

            let activeConvId = null;
            let conversations = [];
            let messageIds = new Set();

            function escapeHtml(s) {
                return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
            }

            function fmtTime(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                const now = new Date();
                const sameDay = d.toDateString() === now.toDateString();
                if (sameDay) return d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                return d.toLocaleDateString([], {day: '2-digit', month: 'short'});
            }

            function renderConvList() {
                const q = convSearch.value.trim().toLowerCase();
                const filtered = q
                    ? conversations.filter(c =>
                        (c.contact_name || '').toLowerCase().includes(q) ||
                        (c.contact_identifier || '').toLowerCase().includes(q))
                    : conversations;

                if (filtered.length === 0) {
                    convListEl.innerHTML = '<div class="p-6 text-center text-sm text-gray-500">Sin conversaciones</div>';
                    return;
                }

                convListEl.innerHTML = filtered.map(c => {
                    const active = c.id === activeConvId;
                    const unreadBadge = c.unread_count > 0
                        ? `<span class="inline-flex items-center justify-center w-5 h-5 text-xs bg-green-500 text-white rounded-full">${c.unread_count}</span>`
                        : '';
                    const preview = c.last_message_preview
                        ? (c.last_message_direction === 'outbound' ? '✓ ' : '') + escapeHtml(c.last_message_preview.slice(0, 60))
                        : 'Sin mensajes';
                    return `
                        <div class="conv-item px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/30 ${active ? 'bg-green-50 dark:bg-green-900/20 border-l-2 border-green-500' : ''}"
                             data-id="${c.id}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate ${c.unread_count > 0 ? 'font-bold' : ''}">
                                    ${escapeHtml(c.contact_name || c.contact_identifier)}
                                </span>
                                <span class="text-xs text-gray-400 shrink-0">${fmtTime(c.last_message_at)}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 mt-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400 truncate">${preview}</span>
                                ${unreadBadge}
                            </div>
                        </div>
                    `;
                }).join('');

                convListEl.querySelectorAll('.conv-item').forEach(el => {
                    el.addEventListener('click', () => loadConversation(parseInt(el.dataset.id, 10)));
                });
            }

            async function loadConversations() {
                try {
                    const res = await fetch(CONV_LIST_URL, { headers: {'Accept': 'application/json'} });
                    const data = await res.json();
                    conversations = data.conversations || [];
                    renderConvList();
                } catch (err) {
                    console.error('loadConversations failed', err);
                }
            }

            function renderMessage(m) {
                if (messageIds.has(m.id)) return;
                messageIds.add(m.id);
                const isOut = m.direction === 'outbound';
                const bubble = document.createElement('div');
                bubble.className = `flex ${isOut ? 'justify-end' : 'justify-start'}`;
                bubble.innerHTML = `
                    <div class="${isOut ? 'bg-green-100 dark:bg-green-900/40' : 'bg-white dark:bg-gray-800'} max-w-[75%] px-3 py-2 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap break-words">${escapeHtml(m.body || '')}</p>
                        <p class="text-[10px] text-gray-400 mt-1 text-right">${fmtTime(m.created_at)}</p>
                    </div>
                `;
                chatMessages.appendChild(bubble);
            }

            async function loadConversation(convId) {
                activeConvId = convId;
                messageIds = new Set();
                chatMessages.innerHTML = '';

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

                    // mark as read
                    fetch(`${CONV_BASE}/${convId}/read`, {
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
                    });
                } catch (err) {
                    console.error('loadConversation failed', err);
                }
            }

            chatCloseMobile.addEventListener('click', () => {
                chatPane.classList.add('hidden');
                chatPane.classList.remove('flex');
                convPane.classList.remove('hidden');
                activeConvId = null;
                renderConvList();
            });

            async function refreshCurrentConversation() {
                if (!activeConvId) return;
                try {
                    const res = await fetch(`${CONV_BASE}/${activeConvId}`, { headers: {'Accept':'application/json'} });
                    const data = await res.json();
                    (data.messages || []).forEach(renderMessage);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                } catch (err) { /* silent */ }
            }

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
                        headers: {
                            'X-CSRF-TOKEN': CSRF,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({body}),
                    });
                    if (!res.ok) throw new Error('send failed');
                    // No render optimista — el servidor es única fuente.
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

            convSearch.addEventListener('input', renderConvList);

            // Polling
            loadConversations();
            setInterval(async () => {
                await loadConversations();
                if (activeConvId) {
                    try {
                        const res = await fetch(`${CONV_BASE}/${activeConvId}`, { headers: {'Accept':'application/json'} });
                        const data = await res.json();
                        (data.messages || []).forEach(renderMessage);
                    } catch (err) { /* silent */ }
                }
            }, 5000);
        })();
    </script>
    @endpush
</x-app-layout>
