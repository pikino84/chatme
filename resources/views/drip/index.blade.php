<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <h2 class="text-xl font-semibold">Secuencias Drip</h2>
            @can('drip.create')
                <a href="{{ route('drip.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Nueva Secuencia</a>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pasos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inscritos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creada</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($sequences as $sequence)
                            <tr>
                                <td class="px-6 py-4">
                                    <a href="{{ route('drip.show', $sequence) }}" class="text-indigo-600 hover:underline">{{ $sequence->name }}</a>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $sequence->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($sequence->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">{{ $sequence->steps_count }}</td>
                                <td class="px-6 py-4">{{ $sequence->enrollments_count }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $sequence->created_at->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('drip.show', $sequence) }}" class="text-indigo-600 hover:underline text-sm">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">No hay secuencias drip.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $sequences->links() }}</div>
        </div>
    </div>
</x-app-layout>
