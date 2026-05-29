<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            WhatsApp Directo
        </h2>
    </x-slot>

    <div class="py-6 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="mb-4 p-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Vincula WhatsApp de tu celular (personal o Business) escaneando un QR.
                    Solo para conversaciones — sin envíos masivos.
                </p>
            </div>
            <button type="button" onclick="document.getElementById('new-channel-modal').classList.remove('hidden')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Vincular nuevo número
            </button>
        </div>

        @if ($channels->isEmpty())
            <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-lg border border-dashed border-gray-300 dark:border-gray-600">
                <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">No hay números vinculados</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Haz click en «Vincular nuevo número» y escanea el QR desde tu WhatsApp.
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($channels as $channel)
                    @php
                        $s = $channel->whatsappWebSession;
                        $status = $s?->status ?? 'disconnected';
                        [$dotColor, $label] = match ($status) {
                            'connected' => ['bg-green-500', 'Conectado'],
                            'qr_pending' => ['bg-yellow-500', 'QR pendiente'],
                            'connecting' => ['bg-yellow-500', 'Conectando'],
                            'logged_out' => ['bg-red-500', 'Sesión cerrada'],
                            'error' => ['bg-red-500', 'Error'],
                            default => ['bg-gray-400', 'Desconectado'],
                        };
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div class="min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ $channel->name }}
                                </h3>
                                @if ($s?->connected_phone)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                        +{{ $s->connected_phone }}
                                    </p>
                                @endif
                            </div>
                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300 shrink-0 ml-2">
                                <span class="w-2 h-2 rounded-full {{ $dotColor }}"></span>
                                {{ $label }}
                            </span>
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            @if ($s?->last_connected_at)
                                Conectado {{ $s->last_connected_at->diffForHumans() }}
                            @elseif ($s?->last_disconnected_at)
                                Desconectado {{ $s->last_disconnected_at->diffForHumans() }}
                            @else
                                Nunca conectado
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($status === 'connected')
                                <a href="{{ route('whatsapp-directo.show', $channel) }}"
                                   class="flex-1 text-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm rounded">
                                    Abrir chats
                                </a>
                                <button type="button"
                                    data-analyze="{{ route('whatsapp-directo.import.analyze', $channel) }}"
                                    data-import="{{ route('whatsapp-directo.import', $channel) }}"
                                    data-conversations="{{ route('whatsapp-directo.conversations', $channel) }}"
                                    onclick="openImportModal(this)"
                                    title="Importar historial exportado de WhatsApp"
                                    class="shrink-0 px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm rounded inline-flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Importar
                                </button>
                            @else
                                <a href="{{ route('whatsapp-directo.pair', $channel) }}"
                                   class="flex-1 text-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                                    {{ $status === 'qr_pending' ? 'Ver QR' : 'Vincular' }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Modal: crear canal nuevo --}}
    <div id="new-channel-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
            <form method="POST" action="{{ route('whatsapp-directo.store') }}">
                @csrf
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Vincular nuevo WhatsApp
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Dale un nombre a este número (ej. «Ventas» o «Soporte»).
                    </p>
                </div>
                <div class="p-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Nombre del canal
                    </label>
                    <input type="text" name="name" required maxlength="120"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="Ej. Ventas MX" autofocus>

                    <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded text-xs text-yellow-800 dark:text-yellow-200">
                        <strong>⚠ No oficial:</strong> este canal usa el protocolo de WhatsApp Web.
                        WhatsApp permite máximo 4 dispositivos vinculados por número.
                        No envíes mensajes masivos desde este canal.
                    </div>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2 rounded-b-lg">
                    <button type="button" onclick="document.getElementById('new-channel-modal').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded">
                        Crear y vincular
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: importar historial (Fase 22.6) --}}
    <div id="import-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Importar historial</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Sube el ZIP exportado desde WhatsApp (Ajustes → Chat → Exportar chat → Incluir multimedia).
                    </p>
                </div>
                <button type="button" onclick="closeImportModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl leading-none">&times;</button>
            </div>

            {{-- Paso 1: subir + analizar --}}
            <div id="import-step1" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivo exportado (.zip o .txt)</label>
                    <input type="file" id="import-file" accept=".zip,.txt"
                        class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded file:border-0 file:bg-green-600 file:text-white hover:file:bg-green-700">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" id="import-dayfirst" checked class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                    Las fechas vienen en formato día/mes (México). Desmarca si son mes/día (EE. UU.).
                </label>
                <div id="import-error1" class="hidden text-sm text-red-600 dark:text-red-400"></div>
                <div class="flex justify-end">
                    <button type="button" id="import-analyze-btn" onclick="runAnalyze()"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded disabled:opacity-50">
                        Analizar archivo
                    </button>
                </div>
            </div>

            {{-- Paso 2: confirmar --}}
            <div id="import-step2" class="hidden p-6 space-y-5">
                <div id="import-summary" class="text-sm bg-gray-50 dark:bg-gray-900 rounded p-3 text-gray-700 dark:text-gray-300"></div>

                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">¿Cuál de estos eres tú? (tus mensajes saldrán como enviados)</p>
                    <div id="import-senders" class="space-y-1.5"></div>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Destino</p>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 mb-2">
                        <input type="radio" name="import-target" value="new" checked onchange="toggleImportTarget()" class="text-green-600 focus:ring-green-500">
                        Nuevo contacto
                    </label>
                    <div id="import-target-new" class="pl-6 space-y-2 mb-3">
                        <input type="text" id="import-phone" placeholder="Teléfono con código de país (ej. 5219981234567)"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded">
                        <input type="text" id="import-name" placeholder="Nombre del contacto (opcional)"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 mb-2">
                        <input type="radio" name="import-target" value="existing" onchange="toggleImportTarget()" class="text-green-600 focus:ring-green-500">
                        Conversación existente
                    </label>
                    <div id="import-target-existing" class="hidden pl-6">
                        <select id="import-conversation" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded">
                            <option value="">Cargando…</option>
                        </select>
                    </div>
                </div>

                <div id="import-error2" class="hidden text-sm text-red-600 dark:text-red-400"></div>
                <div id="import-result" class="hidden text-sm text-green-700 dark:text-green-400"></div>

                <div class="flex justify-between gap-2">
                    <button type="button" onclick="backToStep1()" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                        ← Cambiar archivo
                    </button>
                    <button type="button" id="import-run-btn" onclick="runImport()"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded disabled:opacity-50">
                        Importar
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        var importCtx = { analyze: null, import: null, conversations: null, token: null, dayFirst: true };
        var CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        function openImportModal(btn) {
            importCtx.analyze = btn.dataset.analyze;
            importCtx.import = btn.dataset.import;
            importCtx.conversations = btn.dataset.conversations;
            importCtx.token = null;
            resetImportUI();
            document.getElementById('import-modal').classList.remove('hidden');
        }
        function closeImportModal() {
            document.getElementById('import-modal').classList.add('hidden');
        }
        function resetImportUI() {
            document.getElementById('import-step1').classList.remove('hidden');
            document.getElementById('import-step2').classList.add('hidden');
            document.getElementById('import-file').value = '';
            ['import-error1','import-error2','import-result'].forEach(function(id){
                var el = document.getElementById(id); el.classList.add('hidden'); el.textContent = '';
            });
        }
        function backToStep1() { resetImportUI(); }

        function showErr(id, msg) {
            var el = document.getElementById(id);
            el.textContent = msg; el.classList.remove('hidden');
        }
        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
        function fmtDate(iso) { if (!iso) return '—'; try { return new Date(iso).toLocaleDateString(); } catch(e){ return iso; } }

        function runAnalyze() {
            var fileInput = document.getElementById('import-file');
            document.getElementById('import-error1').classList.add('hidden');
            if (!fileInput.files.length) { showErr('import-error1', 'Selecciona un archivo primero.'); return; }

            importCtx.dayFirst = document.getElementById('import-dayfirst').checked;
            var fd = new FormData();
            fd.append('file', fileInput.files[0]);
            fd.append('day_first', importCtx.dayFirst ? '1' : '0');

            var btn = document.getElementById('import-analyze-btn');
            btn.disabled = true; btn.textContent = 'Analizando…';

            fetch(importCtx.analyze, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd })
                .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, j: j }; }); })
                .then(function(res){
                    if (!res.ok) { showErr('import-error1', res.j.error || 'No se pudo analizar el archivo.'); return; }
                    importCtx.token = res.j.token;
                    renderStep2(res.j);
                })
                .catch(function(){ showErr('import-error1', 'Error de red al analizar.'); })
                .finally(function(){ btn.disabled = false; btn.textContent = 'Analizar archivo'; });
        }

        function renderStep2(data) {
            var s = data.summary;
            var html = '<strong>' + s.total + '</strong> mensajes · <strong>' + s.media_count + '</strong> multimedia · '
                + fmtDate(s.first_ts) + ' → ' + fmtDate(s.last_ts);
            if (data.truncated) { html += '<br><span class="text-yellow-600">Se importarán los primeros 20,000 mensajes.</span>'; }
            document.getElementById('import-summary').innerHTML = html;

            // Remitentes como radios; preselecciona el sugerido (nombre de la cuenta conectada).
            var sendersEl = document.getElementById('import-senders');
            sendersEl.innerHTML = '';
            var names = Object.keys(s.senders);
            var suggested = data.suggested_me;
            names.forEach(function(name, i){
                var checked = (suggested && name === suggested) ? 'checked' : (!suggested && i === 0 ? 'checked' : '');
                var id = 'sender-' + i;
                sendersEl.insertAdjacentHTML('beforeend',
                    '<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">'
                    + '<input type="radio" name="import-me" value="' + esc(name) + '" ' + checked + ' class="text-green-600 focus:ring-green-500">'
                    + esc(name) + ' <span class="text-xs text-gray-400">(' + s.senders[name] + ')</span></label>');
            });
            // Si ninguno quedó marcado, marca el primero.
            if (!sendersEl.querySelector('input:checked') && sendersEl.querySelector('input')) {
                sendersEl.querySelector('input').checked = true;
            }

            document.getElementById('import-step1').classList.add('hidden');
            document.getElementById('import-step2').classList.remove('hidden');
            loadConversationsForImport();
        }

        function toggleImportTarget() {
            var mode = document.querySelector('input[name="import-target"]:checked').value;
            document.getElementById('import-target-new').classList.toggle('hidden', mode !== 'new');
            document.getElementById('import-target-existing').classList.toggle('hidden', mode !== 'existing');
        }

        function loadConversationsForImport() {
            var sel = document.getElementById('import-conversation');
            fetch(importCtx.conversations, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    var list = data.conversations || [];
                    if (!list.length) { sel.innerHTML = '<option value="">(sin conversaciones)</option>'; return; }
                    sel.innerHTML = list.map(function(c){
                        return '<option value="' + c.id + '">' + esc(c.contact_name || c.contact_identifier) + ' (+' + esc(c.contact_identifier) + ')</option>';
                    }).join('');
                })
                .catch(function(){ sel.innerHTML = '<option value="">(error al cargar)</option>'; });
        }

        function runImport() {
            document.getElementById('import-error2').classList.add('hidden');
            document.getElementById('import-result').classList.add('hidden');

            var meEl = document.querySelector('input[name="import-me"]:checked');
            var mode = document.querySelector('input[name="import-target"]:checked').value;

            var payload = {
                token: importCtx.token,
                day_first: importCtx.dayFirst ? 1 : 0,
                me_sender: meEl ? meEl.value : ''
            };
            if (mode === 'existing') {
                var cid = document.getElementById('import-conversation').value;
                if (!cid) { showErr('import-error2', 'Selecciona una conversación.'); return; }
                payload.conversation_id = cid;
            } else {
                var phone = document.getElementById('import-phone').value.trim();
                if (!phone) { showErr('import-error2', 'Escribe el teléfono del contacto.'); return; }
                payload.phone = phone;
                payload.contact_name = document.getElementById('import-name').value.trim();
            }

            var btn = document.getElementById('import-run-btn');
            btn.disabled = true; btn.textContent = 'Importando…';

            fetch(importCtx.import, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, j: j }; }); })
                .then(function(res){
                    if (!res.ok) { showErr('import-error2', res.j.error || 'No se pudo importar.'); return; }
                    var el = document.getElementById('import-result');
                    el.innerHTML = '✓ Importados ' + res.j.imported + ' mensajes'
                        + (res.j.media_imported ? ' (' + res.j.media_imported + ' multimedia)' : '')
                        + (res.j.skipped ? ', ' + res.j.skipped + ' ya existían' : '') + '.';
                    el.classList.remove('hidden');
                    btn.textContent = 'Importar otro';
                    btn.disabled = false;
                })
                .catch(function(){ showErr('import-error2', 'Error de red al importar.'); btn.disabled = false; btn.textContent = 'Importar'; });
        }
    </script>
    @endpush
</x-app-layout>
