<x-app-layout>
    <x-slot name="header">Seleccionar Destinatarios - {{ $campaign->name }}</x-slot>

    <form method="POST" action="{{ route('campaigns.recipients.add', $campaign) }}">
        @csrf
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">Selecciona los contactos con telefono a agregar</p>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Agregar Seleccionados</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefono</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($contacts as $contact)
                            <tr>
                                <td class="px-5 py-3"><input type="checkbox" name="contact_ids[]" value="{{ $contact->id }}" class="contact-cb"></td>
                                <td class="px-5 py-3 text-sm text-gray-900 dark:text-gray-200">{{ $contact->name }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600">{{ $contact->phone }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600">{{ $contact->company ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No hay contactos disponibles con telefono</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($contacts->hasPages())
                <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700">{{ $contacts->links('pagination::simple-tailwind') }}</div>
            @endif
        </div>
    </form>

    <script>
        function toggleAll(source) {
            document.querySelectorAll('.contact-cb').forEach(cb => cb.checked = source.checked);
        }
    </script>
</x-app-layout>
