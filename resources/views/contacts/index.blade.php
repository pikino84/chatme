<x-app-layout>
    <x-slot name="header">Contactos</x-slot>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('contacts.index') }}" class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre, email, telefono..."
                   class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm w-80">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Buscar</button>
        </form>
        <div class="flex gap-2">
            @can('contacts.import')
                <a href="{{ route('contacts.import.form') }}" class="px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700">Importar CSV</a>
            @endcan
            @can('contacts.create')
                <a href="{{ route('contacts.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Nuevo Contacto</a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefono</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($contacts as $contact)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-5 py-3 text-sm">
                                <a href="{{ route('contacts.show', $contact) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ $contact->name }}</a>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $contact->email ?? '-' }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $contact->phone ?? '-' }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $contact->company ?? '-' }}</td>
                            <td class="px-5 py-3 text-sm text-gray-500">{{ $contact->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">Sin contactos</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($contacts->hasPages())
            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $contacts->appends(request()->query())->links('pagination::simple-tailwind') }}
            </div>
        @endif
    </div>
</x-app-layout>
