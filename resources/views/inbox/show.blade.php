<x-app-layout>
    <div class="flex relative overflow-hidden sm:-m-6" style="height: calc(100vh - 64px);" id="conversation-container">

        {{-- Left: Conversation List (desktop only) --}}
        <div class="hidden lg:flex lg:w-80 border-r border-gray-200 dark:border-gray-700 flex-col min-h-0 bg-white dark:bg-gray-800 shrink-0">
            <div class="p-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Conversaciones</h2>
                <a href="{{ route('inbox') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    Bandeja completa &rarr;
                </a>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
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
                {{-- Select mode (WhatsApp only) --}}
                @if($conversation->channel->type === 'whatsapp')
                <button id="btn-select-mode" onclick="toggleSelectMode()" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-xs font-medium shrink-0 transition-colors active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <span>Seleccionar</span>
                </button>
                @endif
                {{-- Info icon --}}
                <button onclick="toggleMetadataPanel()" class="flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0 transition-colors active:scale-95">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
            </div>

            {{-- Messages --}}
            @include('inbox.partials.message-thread')

            {{-- Forward bar --}}
            <div id="forward-bar" class="hidden border-t border-gray-200 bg-green-50 dark:bg-green-900/20 px-4 py-3 flex items-center justify-between">
                <span class="text-sm text-gray-700 dark:text-gray-300"><span id="forward-count">0</span> seleccionados</span>
                <button onclick="openForwardModal()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition flex items-center gap-1 active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    Reenviar
                </button>
            </div>

            {{-- Template banner when 24h expired --}}
            @if($conversation->channel->isWhatsApp() && !$conversation->messages()->where('direction','inbound')->where('created_at','>=',now()->subHours(24))->exists())
                <div class="border-t border-gray-200 bg-yellow-50 dark:bg-yellow-900/20 px-4 py-2 flex items-center justify-between">
                    <span class="text-xs text-yellow-700 dark:text-yellow-300">Ventana 24h expirada</span>
                    <button onclick="openTemplateSendModal({{ $conversation->id }}, {{ $conversation->channel_id }})" class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-xs font-medium transition active:scale-95">Enviar Plantilla</button>
                </div>
            @endif

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

    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeMediaModal(); if (typeof closeForwardModal === 'function') closeForwardModal(); } });

    // ========== FORWARD MESSAGES FEATURE ==========
    var selectMode = false;
    var forwardRecipients = [];
    var showConvId = {{ $conversation->id }};
    var showChannelId = {{ $conversation->channel_id }};
    var showCsrfToken = '{{ csrf_token() }}';

    function toggleSelectMode() {
        selectMode = !selectMode;
        var thread = document.getElementById('message-thread');
        var btn = document.getElementById('btn-select-mode');
        var bar = document.getElementById('forward-bar');
        if (!thread) return;
        if (selectMode) {
            thread.classList.add('select-mode');
            if (btn) btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg><span>Cancelar</span>';
        } else {
            thread.classList.remove('select-mode');
            thread.querySelectorAll('.forward-check').forEach(function(cb) { cb.checked = false; });
            if (btn) btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg><span>Seleccionar</span>';
            if (bar) bar.classList.add('hidden');
        }
    }

    function updateForwardCount() {
        var checked = document.querySelectorAll('.forward-check:checked');
        var countEl = document.getElementById('forward-count');
        var bar = document.getElementById('forward-bar');
        if (countEl) countEl.textContent = checked.length;
        if (bar) {
            if (checked.length > 0 && selectMode) bar.classList.remove('hidden');
            else bar.classList.add('hidden');
        }
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('forward-check')) updateForwardCount();
    });

    function escShow(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML.replace(/'/g, '&#39;');
    }

    function openForwardModal() {
        forwardRecipients = [];
        var modal = document.getElementById('forward-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        document.getElementById('fwd-search-input').value = '';
        document.getElementById('fwd-search-results').innerHTML = '';
        document.getElementById('fwd-manual-phone').value = '';
        renderRecipientChips();
        updateFwdSendBtn();
        searchContacts('');
    }

    function closeForwardModal() {
        var modal = document.getElementById('forward-modal');
        if (modal) modal.classList.add('hidden');
    }

    var fwdSearchTimer = null;
    function onFwdSearchInput(val) {
        clearTimeout(fwdSearchTimer);
        fwdSearchTimer = setTimeout(function() { searchContacts(val); }, 300);
    }

    function searchContacts(q) {
        var url = '/contacts/search?q=' + encodeURIComponent(q);
        if (showChannelId) url += '&channel_id=' + showChannelId;
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(contacts) {
            var container = document.getElementById('fwd-search-results');
            if (!contacts.length) {
                container.innerHTML = '<div class="p-3 text-sm text-gray-400 text-center">No se encontraron contactos</div>';
                return;
            }
            var html = '';
            contacts.forEach(function(c) {
                var isSelected = forwardRecipients.some(function(r) { return r.phone === c.phone; });
                var hasWindow = c.has_active_window !== undefined ? c.has_active_window : null;
                var windowClass = hasWindow === false ? ' opacity-60' : '';
                html += '<div class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition' + windowClass + '" onclick="toggleRecipient(\'' + escShow(c.phone) + '\', \'' + escShow(c.name) + '\')">';
                var avatarColor = hasWindow === false ? 'bg-gray-200 text-gray-500' : 'bg-green-100 text-green-700';
                html += '<div class="w-8 h-8 rounded-full ' + avatarColor + ' flex items-center justify-center text-xs font-bold shrink-0">' + escShow(c.name.charAt(0).toUpperCase()) + '</div>';
                html += '<div class="flex-1 min-w-0"><div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">' + escShow(c.name) + '</div>';
                html += '<div class="text-xs text-gray-500 dark:text-gray-400">' + escShow(c.phone);
                if (hasWindow === false) html += ' <span class="text-red-400">· Sin ventana 24h</span>';
                else if (hasWindow === true) html += ' <span class="text-green-500">· Activo</span>';
                html += '</div></div>';
                if (isSelected) {
                    html += '<svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>';
                }
                html += '</div>';
            });
            container.innerHTML = html;
        })
        .catch(function() {});
    }

    function toggleRecipient(phone, name) {
        var idx = forwardRecipients.findIndex(function(r) { return r.phone === phone; });
        if (idx >= 0) forwardRecipients.splice(idx, 1);
        else forwardRecipients.push({ phone: phone, name: name });
        renderRecipientChips();
        updateFwdSendBtn();
        searchContacts(document.getElementById('fwd-search-input').value);
    }

    function addManualPhone() {
        var input = document.getElementById('fwd-manual-phone');
        var phone = input.value.trim().replace(/\s/g, '');
        if (!phone) return;
        if (!/^\+?\d{10,15}$/.test(phone)) {
            input.classList.add('border-red-500');
            setTimeout(function() { input.classList.remove('border-red-500'); }, 2000);
            return;
        }
        if (!forwardRecipients.some(function(r) { return r.phone === phone; })) {
            forwardRecipients.push({ phone: phone, name: phone });
        }
        input.value = '';
        renderRecipientChips();
        updateFwdSendBtn();
    }

    function removeRecipient(phone) {
        forwardRecipients = forwardRecipients.filter(function(r) { return r.phone !== phone; });
        renderRecipientChips();
        updateFwdSendBtn();
        searchContacts(document.getElementById('fwd-search-input').value);
    }

    function renderRecipientChips() {
        var container = document.getElementById('fwd-chips');
        if (!forwardRecipients.length) {
            container.innerHTML = '<span class="text-xs text-gray-400">Ningún destinatario seleccionado</span>';
            return;
        }
        var html = '';
        forwardRecipients.forEach(function(r) {
            html += '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-100 text-green-800 text-xs">';
            html += escShow(r.name === r.phone ? r.phone : r.name);
            html += '<button type="button" onclick="removeRecipient(\'' + escShow(r.phone) + '\')" class="w-4 h-4 rounded-full hover:bg-green-200 flex items-center justify-center">';
            html += '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            html += '</button></span>';
        });
        container.innerHTML = html;
    }

    function updateFwdSendBtn() {
        var btn = document.getElementById('fwd-send-btn');
        if (!btn) return;
        if (forwardRecipients.length > 0) {
            btn.disabled = false;
            btn.textContent = 'Reenviar a ' + forwardRecipients.length + (forwardRecipients.length === 1 ? ' contacto' : ' contactos');
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            btn.disabled = true;
            btn.textContent = 'Selecciona destinatarios';
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    function submitForward() {
        if (!forwardRecipients.length) return;
        var checked = document.querySelectorAll('.forward-check:checked');
        var msgIds = [];
        checked.forEach(function(cb) { msgIds.push(parseInt(cb.value, 10)); });
        if (!msgIds.length) return;

        var btn = document.getElementById('fwd-send-btn');
        btn.disabled = true;
        btn.textContent = 'Enviando...';

        fetch('/inbox/conversations/' + showConvId + '/messages/forward', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': showCsrfToken
            },
            body: JSON.stringify({
                message_ids: msgIds,
                recipients: forwardRecipients.map(function(r) { return r.phone; })
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                closeForwardModal();
                toggleSelectMode();
                if (data.skipped && data.skipped.length > 0 && data.sent && data.sent.length > 0) {
                    showForwardToast('Enviados a ' + data.sent.length + '. ' + data.skipped.length + ' sin ventana 24h.');
                } else if (data.skipped && data.skipped.length > 0 && (!data.sent || data.sent.length === 0)) {
                    showForwardToast('No se pudo reenviar: los destinatarios no tienen ventana activa de 24h.', true);
                } else {
                    showForwardToast('Mensajes reenviados correctamente');
                }
            } else {
                btn.disabled = false;
                btn.textContent = 'Reenviar';
                showForwardToast(data.error || 'Error al reenviar', true);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Reenviar';
            showForwardToast('Error de conexión', true);
        });
    }

    function showForwardToast(message, isError) {
        var toast = document.createElement('div');
        toast.className = 'fixed bottom-4 left-1/2 -translate-x-1/2 px-4 py-2 rounded-lg text-white text-sm shadow-lg z-[70] transition-opacity duration-300 ' + (isError ? 'bg-red-500' : 'bg-green-500');
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function() { toast.classList.add('opacity-0'); }, 2500);
        setTimeout(function() { toast.remove(); }, 3000);
    }

    // ========== TEMPLATE SEND FEATURE ==========
    var tplSendConvId = null;
    var tplSendChannelId = null;
    var tplSelected = null;

    function openTemplateSendModal(convId, channelId) {
        tplSendConvId = convId;
        tplSendChannelId = channelId;
        tplSelected = null;
        var modal = document.getElementById('template-send-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        document.getElementById('tpl-send-step1').classList.remove('hidden');
        document.getElementById('tpl-send-step2').classList.add('hidden');
        document.getElementById('tpl-send-list').innerHTML = '<div class="p-4 text-center text-gray-400 text-sm">Cargando...</div>';

        fetch('/whatsapp-templates/approved?channel_id=' + channelId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(templates) {
            var container = document.getElementById('tpl-send-list');
            if (!templates.length) {
                container.innerHTML = '<div class="p-4 text-center text-gray-400 text-sm">No hay plantillas aprobadas.</div>';
                return;
            }
            var html = '';
            templates.forEach(function(t) {
                html += '<div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition border-b border-gray-100 dark:border-gray-700" onclick=\'selectTemplate(' + JSON.stringify(t).replace(/'/g, "\\'") + ')\'>';
                html += '<div class="flex items-center justify-between mb-1"><span class="text-sm font-medium text-gray-800 dark:text-gray-200">' + escShow(t.name) + '</span>';
                html += '<span class="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700">' + escShow(t.category) + '</span></div>';
                html += '<p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">' + escShow(t.body_text) + '</p></div>';
            });
            container.innerHTML = html;
        }).catch(function() {
            document.getElementById('tpl-send-list').innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Error al cargar</div>';
        });
    }

    function closeTemplateSendModal() {
        var modal = document.getElementById('template-send-modal');
        if (modal) modal.classList.add('hidden');
    }

    function selectTemplate(t) {
        tplSelected = t;
        document.getElementById('tpl-send-step1').classList.add('hidden');
        document.getElementById('tpl-send-step2').classList.remove('hidden');
        document.getElementById('tpl-send-name').textContent = t.name;
        var varsContainer = document.getElementById('tpl-send-vars');
        varsContainer.innerHTML = '';
        if (t.variable_count > 0) {
            for (var i = 1; i <= t.variable_count; i++) {
                var div = document.createElement('div');
                div.className = 'flex items-center gap-2 mb-2';
                div.innerHTML = '<span class="text-xs text-gray-500 w-12 shrink-0">{{' + i + '}}</span>' +
                    '<input type="text" class="tpl-var-input flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-green-500" placeholder="Valor ' + i + '">';
                varsContainer.appendChild(div);
            }
        } else {
            varsContainer.innerHTML = '<p class="text-xs text-gray-400">Sin variables.</p>';
        }
        updateTplSendPreview();
    }

    function backToTemplateList() {
        document.getElementById('tpl-send-step1').classList.remove('hidden');
        document.getElementById('tpl-send-step2').classList.add('hidden');
    }

    function updateTplSendPreview() {
        if (!tplSelected) return;
        var body = tplSelected.body_text;
        document.querySelectorAll('.tpl-var-input').forEach(function(inp, i) {
            body = body.replace('{{' + (i + 1) + '}}', inp.value || '[' + (i + 1) + ']');
        });
        document.getElementById('tpl-send-preview').textContent = body;
    }

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('tpl-var-input')) updateTplSendPreview();
    });

    function submitTemplateSend() {
        if (!tplSelected || !tplSendConvId) return;
        var variables = [];
        document.querySelectorAll('.tpl-var-input').forEach(function(inp) { variables.push(inp.value); });

        var btn = document.getElementById('tpl-send-submit');
        btn.disabled = true;
        btn.textContent = 'Enviando...';

        fetch('/inbox/conversations/' + tplSendConvId + '/send-template', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': showCsrfToken },
            body: JSON.stringify({ template_id: tplSelected.id, variables: variables })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.message) {
                closeTemplateSendModal();
                showForwardToast('Plantilla enviada correctamente');
                // Reload to see the message
                window.location.reload();
            } else {
                btn.disabled = false;
                btn.textContent = 'Enviar plantilla';
                showForwardToast(data.error || 'Error al enviar', true);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Enviar plantilla';
            showForwardToast('Error de conexión', true);
        });
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

        // Convierte URLs en texto ya escapado a enlaces target="_blank"
        function linkify(escaped) {
            return escaped.replace(/((?:https?:\/\/|www\.)[^\s<]+)/gi, function(m) {
                var url = m, trail = '';
                var tm = url.match(/[.,;:!?)\]}'"]+$/);
                if (tm) { trail = tm[0]; url = url.slice(0, -trail.length); }
                var href = /^https?:\/\//i.test(url) ? url : 'http://' + url;
                return '<a href="' + href + '" target="_blank" rel="noopener noreferrer" class="underline break-all">' + url + '</a>' + trail;
            });
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
            return ['[Image]', '[Audio]', '[Video]', '[Document]', '[Sticker]'].indexOf(body) !== -1;
        }

        function renderAttachment(att) {
            if (att.status === 'pending' || att.status === 'processing') {
                return '<div class="flex items-center gap-2 py-2 text-xs opacity-60"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Descargando ' + escapeHtml(att.media_type) + '...</div>';
            }
            if (att.status === 'failed') {
                return '<div class="flex items-center gap-2 py-2 text-xs text-red-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Error al descargar</div>';
            }
            if (att.media_type === 'sticker' && att.url) {
                return '<img src="' + escapeHtml(att.url) + '" alt="Sticker" class="w-28 h-28 object-contain cursor-pointer hover:scale-105 transition mb-1" loading="lazy" onclick="openMediaModal(\'image\', \'' + escapeHtml(att.url) + '\', \'sticker.webp\')">';
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

        var pendingMediaMsgs = {};
        var soundReady = false;
        setTimeout(function() { soundReady = true; }, 5000);

        function needsMediaUpdate(msg) {
            // Has pending attachments
            if (msg.attachments && msg.attachments.length > 0) {
                return msg.attachments.some(function(a) { return a.status === 'pending' || a.status === 'processing'; });
            }
            // Body is a media placeholder but no attachments yet (broadcast arrived before attachment was loaded)
            if (isMediaPlaceholder(msg.body)) return true;
            return false;
        }

        function updateMessageMedia(msgId, msg) {
            var el = document.querySelector('[data-msg-id="' + msgId + '"]');
            if (!el) return;
            var hasReady = msg.attachments && msg.attachments.length > 0 &&
                msg.attachments.some(function(a) { return a.status === 'ready'; });
            if (!hasReady) return;

            delete pendingMediaMsgs[msgId];
            var wrapper = el.querySelector('div');
            if (!wrapper) return;
            var mediaHtml = renderAttachments(msg.attachments);
            if (msg.body && !isMediaPlaceholder(msg.body)) {
                mediaHtml += '<p class="mt-1 text-xs opacity-70">' + linkify(escapeHtml(msg.body)) + '</p>';
            }
            var time = msg.time || '';
            if (msg.direction === 'inbound') {
                mediaHtml += '<div class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 text-right">' + time + '</div>';
            } else {
                mediaHtml += '<div class="text-[10px] text-crea-secondary-light mt-1 text-right">' + escapeHtml(msg.user_name || 'Agent') + ' &middot; ' + time + '</div>';
            }
            wrapper.innerHTML = mediaHtml;
        }

        function pollPendingMedia() {
            var ids = Object.keys(pendingMediaMsgs);
            if (!ids.length) return;

            // Use the smallest pending msg id as after_id to get relevant messages
            var minId = Math.min.apply(null, ids.map(Number)) - 1;
            fetch(pollUrl + '?after_id=' + minId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.messages) return;
                data.messages.forEach(function(msg) {
                    if (pendingMediaMsgs[msg.id]) {
                        updateMessageMedia(msg.id, msg);
                    }
                });
            })
            .catch(function() {});
        }

        setInterval(pollPendingMedia, 3000);

        function appendMessage(msg) {
            if (document.querySelector('[data-msg-id="' + msg.id + '"]')) return;
            if (sentIds[msg.id]) {
                delete sentIds[msg.id];
                return;
            }

            var thread = document.getElementById('message-thread');
            if (!thread) return;

            // Remove optimistic duplicate for outbound messages
            if (msg.direction === 'outbound') {
                var optimistics = thread.querySelectorAll('[data-optimistic]');
                optimistics.forEach(function(el) { el.remove(); });
            }

            // Play sound for inbound messages (skip first 5s after page load)
            if (msg.direction === 'inbound' && soundReady && typeof playNotifSound === 'function') {
                playNotifSound();
            }

            var div = document.createElement('div');
            div.setAttribute('data-msg-id', msg.id);
            var time = msg.time || '';

            var hasMedia = msg.attachments && msg.attachments.length > 0;
            var bodyHtml = '';
            if (hasMedia) {
                bodyHtml = renderAttachments(msg.attachments);
                if (msg.body && !isMediaPlaceholder(msg.body)) {
                    bodyHtml += '<p class="mt-1 text-xs opacity-70">' + linkify(escapeHtml(msg.body)) + '</p>';
                }
            } else if (isMediaPlaceholder(msg.body)) {
                // Media placeholder without attachments - show loading spinner
                var mediaType = msg.body.replace('[', '').replace(']', '').toLowerCase();
                bodyHtml = '<div class="flex items-center gap-2 py-2 text-xs opacity-60"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Descargando ' + mediaType + '...</div>';
            } else {
                bodyHtml = linkify(escapeHtml(msg.body));
            }

            // Track messages that need media updates
            if (needsMediaUpdate(msg)) {
                pendingMediaMsgs[msg.id] = true;
            }

            var forwardedHtml = '';
            if (msg.is_forwarded) {
                forwardedHtml = '<div class="text-[10px] italic opacity-60 mb-1 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>Reenviado</div>';
            }
            var cbHtml = '<label class="msg-checkbox hidden self-center shrink-0 cursor-pointer"><input type="checkbox" class="forward-check w-4 h-4 rounded border-gray-300 text-green-500 focus:ring-green-500" value="' + msg.id + '"></label>';

            if (msg.type === 'internal_note') {
                div.className = 'flex justify-center';
                div.innerHTML = '<div class="max-w-md px-3 py-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-xs text-yellow-700 dark:text-yellow-300">' +
                    '<span class="font-medium">' + escapeHtml(msg.user_name || 'System') + ':</span> ' +
                    escapeHtml(msg.body) + ' <span class="text-yellow-400 ml-2">' + time + '</span></div>';
            } else if (msg.direction === 'inbound') {
                div.className = 'flex justify-start items-start gap-1';
                div.innerHTML = cbHtml + '<div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-bl-sm bg-white dark:bg-gray-700 shadow-sm text-sm text-gray-800 dark:text-gray-200">' +
                    forwardedHtml + bodyHtml + '<div class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 text-right">' + time + '</div></div>';
            } else if (msg.type === 'template') {
                div.className = 'flex justify-end items-start gap-1';
                div.innerHTML = '<div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-crea-primary text-white text-sm shadow-sm border border-green-400/30">' +
                    '<div class="text-[10px] italic text-white/60 mb-1 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>Plantilla</div>' +
                    linkify(escapeHtml(msg.body)) + '<div class="text-[10px] text-crea-secondary-light mt-1 text-right">' + escapeHtml(msg.user_name || 'Agent') + ' &middot; ' + time + '</div></div>' + cbHtml;
            } else {
                div.className = 'flex justify-end items-start gap-1';
                div.innerHTML = '<div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-crea-primary text-white text-sm shadow-sm">' +
                    forwardedHtml + bodyHtml + '<div class="text-[10px] text-crea-secondary-light mt-1 text-right">' + escapeHtml(msg.user_name || 'Agent') + ' &middot; ' + time + '</div></div>' + cbHtml;
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
            initEcho();
            setInterval(pollMessages, 5000);
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

    {{-- Forward Modal --}}
    <div id="forward-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50" onclick="closeForwardModal()"></div>
        <div class="relative z-10 bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 max-h-[80vh] flex flex-col">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Reenviar mensajes</h3>
                <button onclick="closeForwardModal()" class="w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-center text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700">
                <input type="text" id="fwd-search-input" placeholder="Buscar contacto por nombre o teléfono..."
                       oninput="onFwdSearchInput(this.value)"
                       class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-green-500 focus:border-green-500">
            </div>
            <div id="fwd-search-results" class="flex-1 overflow-y-auto min-h-0 max-h-[200px] divide-y divide-gray-100 dark:divide-gray-700"></div>
            <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">O ingresar número manualmente:</p>
                <div class="flex gap-2">
                    <input type="text" id="fwd-manual-phone" placeholder="+521234567890"
                           class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-green-500 focus:border-green-500 transition"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();addManualPhone();}">
                    <button onclick="addManualPhone()" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 font-medium transition active:scale-95">
                        + Agregar
                    </button>
                </div>
            </div>
            <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Destinatarios:</p>
                <div id="fwd-chips" class="flex flex-wrap gap-1">
                    <span class="text-xs text-gray-400">Ningún destinatario seleccionado</span>
                </div>
            </div>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex gap-2 justify-end">
                <button onclick="closeForwardModal()" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">Cancelar</button>
                <button id="fwd-send-btn" onclick="submitForward()" disabled class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-medium transition opacity-50 cursor-not-allowed">
                    Selecciona destinatarios
                </button>
            </div>
        </div>
    </div>

    {{-- Template Send Modal --}}
    <div id="template-send-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50" onclick="closeTemplateSendModal()"></div>
        <div class="relative z-10 bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 max-h-[80vh] flex flex-col">
            <div id="tpl-send-step1">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Seleccionar plantilla</h3>
                    <button onclick="closeTemplateSendModal()" class="w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-center text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div id="tpl-send-list" class="flex-1 overflow-y-auto max-h-[50vh]"></div>
            </div>
            <div id="tpl-send-step2" class="hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                    <button onclick="backToTemplateList()" class="text-gray-500 hover:text-gray-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100" id="tpl-send-name"></h3>
                </div>
                <div class="p-4 space-y-3">
                    <div><label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">Variables</label><div id="tpl-send-vars"></div></div>
                    <div><label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">Vista previa</label><div class="bg-[#dcf8c6] rounded-lg p-3 text-sm text-gray-800 whitespace-pre-wrap" id="tpl-send-preview"></div></div>
                </div>
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex gap-2 justify-end">
                    <button onclick="closeTemplateSendModal()" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Cancelar</button>
                    <button id="tpl-send-submit" onclick="submitTemplateSend()" class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-medium transition hover:bg-green-600">Enviar plantilla</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .select-mode .msg-checkbox { display: flex !important; }
    </style>
</x-app-layout>
