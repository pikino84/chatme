<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('whatsapp-directo.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Vincular: {{ $channel->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">

            <div id="status-indicator" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-4">
                <span id="status-dot" class="w-2 h-2 rounded-full bg-gray-400"></span>
                <span id="status-label">Esperando QR...</span>
            </div>

            {{-- Instrucciones --}}
            <div id="instructions" class="mb-6 text-sm text-gray-700 dark:text-gray-300">
                <ol class="list-decimal list-inside space-y-1">
                    <li>Abre <strong>WhatsApp</strong> en tu celular.</li>
                    <li>Ve a <strong>Ajustes</strong> → <strong>Dispositivos vinculados</strong>.</li>
                    <li>Toca <strong>Vincular un dispositivo</strong>.</li>
                    <li>Escanea el QR de abajo.</li>
                </ol>
            </div>

            {{-- QR --}}
            <div class="flex flex-col items-center">
                <div id="qr-container" class="w-72 h-72 bg-white flex items-center justify-center border-2 border-gray-200 dark:border-gray-600 rounded p-4">
                    <div id="qr-loading" class="text-center">
                        <svg class="animate-spin w-10 h-10 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <p class="text-sm text-gray-500">Solicitando QR al bridge...</p>
                    </div>
                    <canvas id="qr-canvas" class="hidden"></canvas>
                </div>

                <p class="mt-3 text-xs text-gray-500">
                    El QR se renueva cada ~20s automáticamente. Deja esta página abierta.
                </p>

                <button type="button" id="refresh-qr" class="mt-4 text-sm text-blue-600 hover:text-blue-700 hidden">
                    Generar QR nuevo
                </button>
            </div>

            {{-- Success state --}}
            <div id="connected-state" class="hidden text-center py-8">
                <svg class="w-16 h-16 text-green-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">¡Vinculado!</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1" id="connected-phone"></p>
                <p class="text-sm text-gray-500 mt-3">Redirigiendo a los chats...</p>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        (function() {
            const CHANNEL_ID = {{ $channel->id }};
            const STATUS_URL = @json(route('whatsapp-directo.status', $channel));
            const REPAIR_URL = @json(route('whatsapp-directo.repair', $channel));
            const SHOW_URL = @json(route('whatsapp-directo.show', $channel));
            const CSRF = @json(csrf_token());

            let lastQr = null;
            let poller = null;

            const dot = document.getElementById('status-dot');
            const label = document.getElementById('status-label');
            const loading = document.getElementById('qr-loading');
            const canvas = document.getElementById('qr-canvas');
            const connected = document.getElementById('connected-state');
            const connectedPhone = document.getElementById('connected-phone');
            const refreshBtn = document.getElementById('refresh-qr');

            refreshBtn.addEventListener('click', async () => {
                refreshBtn.disabled = true;
                await fetch(REPAIR_URL, {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
                    body: JSON.stringify({reset: true}),
                });
                refreshBtn.disabled = false;
                refreshBtn.classList.add('hidden');
                loading.classList.remove('hidden');
                canvas.classList.add('hidden');
                lastQr = null;
            });

            function renderQr(data) {
                if (data === lastQr) return;
                lastQr = data;
                loading.classList.add('hidden');
                canvas.classList.remove('hidden');
                QRCode.toCanvas(canvas, data, { width: 256, margin: 1 }, (err) => {
                    if (err) console.error('QR render error', err);
                });
            }

            function setStatus(color, text) {
                dot.className = 'w-2 h-2 rounded-full ' + color;
                label.textContent = text;
            }

            async function poll() {
                try {
                    const res = await fetch(STATUS_URL, { headers: {'Accept': 'application/json'} });
                    const data = await res.json();

                    switch (data.status) {
                        case 'qr_pending':
                            setStatus('bg-yellow-500', 'QR disponible — escanea con tu WhatsApp');
                            if (data.qr_raw) renderQr(data.qr_raw);
                            refreshBtn.classList.remove('hidden');
                            break;
                        case 'connecting':
                            setStatus('bg-yellow-500', 'Conectando...');
                            break;
                        case 'connected':
                            setStatus('bg-green-500', 'Conectado');
                            document.getElementById('instructions').classList.add('hidden');
                            document.getElementById('qr-container').classList.add('hidden');
                            refreshBtn.classList.add('hidden');
                            connected.classList.remove('hidden');
                            connectedPhone.textContent = '+' + (data.connected_phone || '');
                            clearInterval(poller);
                            setTimeout(() => { window.location.href = SHOW_URL; }, 1500);
                            return;
                        case 'logged_out':
                            setStatus('bg-red-500', 'Sesión cerrada — reinicia el pairing');
                            refreshBtn.classList.remove('hidden');
                            break;
                        case 'error':
                            setStatus('bg-red-500', 'Error');
                            refreshBtn.classList.remove('hidden');
                            break;
                        default:
                            setStatus('bg-gray-400', 'Desconectado');
                            refreshBtn.classList.remove('hidden');
                    }
                } catch (err) {
                    console.error('poll failed', err);
                }
            }

            poll();
            poller = setInterval(poll, 2000);
        })();
    </script>
    @endpush
</x-app-layout>
