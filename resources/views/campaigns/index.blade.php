<x-app-layout>
    <x-slot name="header">Campañas</x-slot>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <div class="mb-6 flex justify-end">
        @can('campaigns.create')
            <a href="{{ route('campaigns.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Nueva Campaña</a>
        @endcan
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Canal</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Destinatarios</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Enviados</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creada</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($campaigns as $campaign)
                        <tr>
                            <td class="px-5 py-3 text-sm">
                                <a href="{{ route('campaigns.show', $campaign) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ $campaign->name }}</a>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $campaign->channel->name ?? '-' }}</td>
                            <td class="px-5 py-3 text-sm">
                                @php $colors = ['draft' => 'bg-gray-100 text-gray-700', 'scheduled' => 'bg-blue-100 text-blue-700', 'running' => 'bg-yellow-100 text-yellow-700', 'completed' => 'bg-green-100 text-green-700', 'canceled' => 'bg-red-100 text-red-700']; @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $colors[$campaign->status] ?? '' }}">{{ ucfirst($campaign->status) }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm text-right text-gray-600">{{ $campaign->total_recipients }}</td>
                            <td class="px-5 py-3 text-sm text-right text-gray-600">{{ $campaign->sent_count }}</td>
                            <td class="px-5 py-3 text-sm text-gray-500">{{ $campaign->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">Sin campañas</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($campaigns->hasPages())
            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700">{{ $campaigns->links('pagination::simple-tailwind') }}</div>
        @endif
    </div>
</x-app-layout>
