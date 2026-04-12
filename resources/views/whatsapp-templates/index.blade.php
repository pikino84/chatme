<x-app-layout>
    <x-slot name="header">Plantillas WhatsApp</x-slot>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        {{-- Sync from Meta --}}
        <form method="POST" action="{{ route('whatsapp-templates.sync') }}" class="flex items-center gap-2">
            @csrf
            <select name="channel_id" required class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                <option value="">Canal...</option>
                @foreach($channels as $ch)
                    <option value="{{ $ch->id }}">{{ $ch->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition">
                Sincronizar desde Meta
            </button>
        </form>

        @can('create', App\Models\WhatsAppTemplate::class)
            <a href="{{ route('whatsapp-templates.create') }}" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                + Crear Plantilla
            </a>
        @endcan
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Idioma</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Canal</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($templates as $tpl)
                        <tr>
                            <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $tpl->name }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $tpl->language }}</td>
                            <td class="px-5 py-3 text-sm">
                                @php $catColors = ['MARKETING' => 'bg-purple-100 text-purple-700', 'UTILITY' => 'bg-blue-100 text-blue-700', 'AUTHENTICATION' => 'bg-orange-100 text-orange-700']; @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $catColors[$tpl->category] ?? 'bg-gray-100 text-gray-700' }}">{{ $tpl->category }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm">
                                @php $statusColors = ['APPROVED' => 'bg-green-100 text-green-700', 'PENDING' => 'bg-yellow-100 text-yellow-700', 'REJECTED' => 'bg-red-100 text-red-700', 'PAUSED' => 'bg-gray-100 text-gray-700', 'DISABLED' => 'bg-gray-100 text-gray-500']; @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $statusColors[$tpl->status] ?? '' }}">{{ $tpl->status }}</span>
                                @if($tpl->rejection_reason)
                                    <p class="text-[10px] text-red-500 mt-1">{{ Str::limit($tpl->rejection_reason, 50) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $tpl->channel->name ?? '-' }}</td>
                            <td class="px-5 py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    @if($tpl->isRejected())
                                        <a href="{{ route('whatsapp-templates.edit', $tpl) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Editar</a>
                                    @endif
                                    @can('delete', $tpl)
                                        <form method="POST" action="{{ route('whatsapp-templates.destroy', $tpl) }}" onsubmit="return confirm('¿Eliminar esta plantilla?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Eliminar</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-500 text-sm">
                                No hay plantillas. Crea una nueva o sincroniza desde Meta.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-t border-gray-200 dark:border-gray-700">
            {{ $templates->links('pagination::simple-tailwind') }}
        </div>
    </div>
</x-app-layout>
