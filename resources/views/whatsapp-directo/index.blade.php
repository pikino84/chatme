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
</x-app-layout>
