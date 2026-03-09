<x-app-layout>
    <x-slot name="header">Importar Contactos</x-slot>

    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Sube un archivo CSV con las columnas: <strong>name</strong> (requerido), email, phone, company, notes.
                La primera fila debe ser el encabezado.
            </p>

            <form method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivo CSV</label>
                    <input type="file" name="csv_file" accept=".csv,.txt" required
                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    @error('csv_file') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Importar</button>
                    <a href="{{ route('contacts.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-300">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
