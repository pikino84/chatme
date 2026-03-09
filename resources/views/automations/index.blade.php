<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <h2 class="text-xl font-semibold">Automatizaciones</h2>
            @can('automations.create')
                <a href="{{ route('automations.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Nueva Regla</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Canal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($rules as $rule)
                            <tr>
                                <td class="px-6 py-4 font-medium">{{ $rule->name }}</td>
                                <td class="px-6 py-4">
                                    @php
                                        $typeLabels = [
                                            'auto_assign' => 'Auto-asignación',
                                            'auto_response' => 'Auto-respuesta',
                                            'stale_alert' => 'Alerta inactividad',
                                        ];
                                    @endphp
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        {{ $typeLabels[$rule->type] ?? $rule->type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $rule->channel?->name ?? 'Todos' }}</td>
                                <td class="px-6 py-4">
                                    <form method="POST" action="{{ route('automations.toggle', $rule) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 text-xs rounded-full {{ $rule->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $rule->is_active ? 'Activa' : 'Inactiva' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    @can('automations.update')
                                        <a href="{{ route('automations.edit', $rule) }}" class="text-indigo-600 hover:underline text-sm">Editar</a>
                                    @endcan
                                    @can('automations.delete')
                                        <form method="POST" action="{{ route('automations.destroy', $rule) }}" class="inline" onsubmit="return confirm('¿Eliminar esta regla?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline text-sm">Eliminar</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">No hay reglas de automatización.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $rules->links() }}</div>
        </div>
    </div>
</x-app-layout>
