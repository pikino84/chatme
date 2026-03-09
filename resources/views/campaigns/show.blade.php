<x-app-layout>
    <x-slot name="header">{{ $campaign->name }}</x-slot>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Estado</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ ucfirst($campaign->status) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Total</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Enviados</p>
            <p class="text-lg font-bold text-green-600">{{ $stats['sent'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Fallidos</p>
            <p class="text-lg font-bold text-red-600">{{ $stats['failed'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase">Tasa Envio</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stats['delivery_rate'] }}%</p>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex gap-3 mb-6">
        @if($campaign->canBeEdited())
            <a href="{{ route('campaigns.edit', $campaign) }}" class="px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700">Editar</a>
            <a href="{{ route('campaigns.recipients.select', $campaign) }}" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Agregar Destinatarios</a>
        @endif
        @if($campaign->canBeSent())
            @can('campaigns.send')
                <form method="POST" action="{{ route('campaigns.send', $campaign) }}" onsubmit="return confirm('Enviar campaña?')">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">Enviar Ahora</button>
                </form>
            @endcan
        @endif
        @if(in_array($campaign->status, ['draft', 'scheduled', 'running']))
            @can('campaigns.send')
                <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">Cancelar</button>
                </form>
            @endcan
        @endif
    </div>

    {{-- Message preview --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Mensaje</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $campaign->message_template }}</p>
        <p class="text-xs text-gray-500 mt-2">Canal: {{ $campaign->channel->name ?? '-' }} ({{ $campaign->channel->type ?? '' }})</p>
    </div>

    {{-- Recipients table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Destinatarios</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contacto</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefono</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enviado</th>
                        @if($campaign->canBeEdited())
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Accion</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($recipients as $recipient)
                        <tr>
                            <td class="px-5 py-3 text-sm text-gray-900 dark:text-gray-200">{{ $recipient->contact->name ?? 'Eliminado' }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600">{{ $recipient->contact->phone ?? '-' }}</td>
                            <td class="px-5 py-3 text-sm">
                                @php $rColors = ['pending' => 'text-gray-500', 'sent' => 'text-green-600', 'delivered' => 'text-green-700', 'failed' => 'text-red-600']; @endphp
                                <span class="{{ $rColors[$recipient->status] ?? '' }}">{{ ucfirst($recipient->status) }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-500">{{ $recipient->sent_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            @if($campaign->canBeEdited())
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('campaigns.recipients.remove', [$campaign, $recipient->contact_id]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-800">Quitar</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">Sin destinatarios</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recipients->hasPages())
            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700">{{ $recipients->links('pagination::simple-tailwind') }}</div>
        @endif
    </div>
</x-app-layout>
