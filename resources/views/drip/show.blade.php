<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <h2 class="text-xl font-semibold">{{ $sequence->name }}</h2>
            <div class="flex gap-2">
                @can('drip.update')
                    @if($sequence->isActive())
                        <form method="POST" action="{{ route('drip.pause', $sequence) }}">
                            @csrf
                            <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">Pausar</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('drip.activate', $sequence) }}">
                            @csrf
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Activar</button>
                        </form>
                    @endif
                    <a href="{{ route('drip.edit', $sequence) }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">Editar</a>
                @endcan
                @can('drip.delete')
                    <form method="POST" action="{{ route('drip.destroy', $sequence) }}" onsubmit="return confirm('¿Eliminar esta secuencia?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Eliminar</button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="p-3 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>
            @endif

            {{-- Info --}}
            <div class="bg-white shadow rounded-lg p-6">
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <dt class="text-sm text-gray-500">Estado</dt>
                        <dd class="font-semibold">{{ ucfirst($sequence->status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Trigger</dt>
                        <dd class="font-semibold">{{ $sequence->trigger_event ?? 'Manual' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Pasos</dt>
                        <dd class="font-semibold">{{ $sequence->steps->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Inscritos activos</dt>
                        <dd class="font-semibold">{{ $enrollments->total() }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Steps --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Pasos de la secuencia</h3>

                @foreach($sequence->steps as $step)
                    <div class="border rounded p-4 mb-3 flex justify-between items-start">
                        <div>
                            <span class="text-sm text-gray-500">Paso {{ $step->position }}</span>
                            <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">{{ $step->channel_type }}</span>
                            <span class="ml-2 text-xs text-gray-400">Espera: {{ $step->delay_minutes }} min</span>
                            <p class="mt-1 text-sm">{{ Str::limit($step->message_template, 120) }}</p>
                        </div>
                        @can('drip.update')
                            <form method="POST" action="{{ route('drip.steps.remove', [$sequence, $step]) }}" onsubmit="return confirm('¿Eliminar este paso?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 text-sm hover:underline">Eliminar</button>
                            </form>
                        @endcan
                    </div>
                @endforeach

                @can('drip.update')
                    <form method="POST" action="{{ route('drip.steps.add', $sequence) }}" class="border-t pt-4 mt-4">
                        @csrf
                        <h4 class="text-sm font-medium mb-2">Agregar paso</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500">Espera (minutos)</label>
                                <input type="number" name="delay_minutes" min="1" value="60" required class="w-full border rounded px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Canal</label>
                                <select name="channel_type" class="w-full border rounded px-3 py-2">
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="webchat">Webchat</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-xs text-gray-500">Mensaje</label>
                            <textarea name="message_template" rows="3" required class="w-full border rounded px-3 py-2"></textarea>
                        </div>
                        <button type="submit" class="mt-2 bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Agregar Paso</button>
                    </form>
                @endcan
            </div>

            {{-- Enrollments --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Contactos inscritos</h3>

                @can('drip.update')
                    @if($sequence->isActive())
                        <form method="POST" action="{{ route('drip.enroll', $sequence) }}" class="mb-4 flex gap-2">
                            @csrf
                            <input type="number" name="contact_id" placeholder="ID del contacto" required class="border rounded px-3 py-2">
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Inscribir</button>
                        </form>
                    @endif
                @endcan

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contacto</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Paso actual</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Próximo paso</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($enrollments as $enrollment)
                            <tr>
                                <td class="px-4 py-2">{{ $enrollment->contact->name ?? 'N/A' }}</td>
                                <td class="px-4 py-2">{{ $enrollment->current_step }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $enrollment->status === 'active' ? 'bg-green-100 text-green-800' : ($enrollment->status === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                                        {{ ucfirst($enrollment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ $enrollment->next_step_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-2 text-right">
                                    @if($enrollment->isActive())
                                        <form method="POST" action="{{ route('drip.enrollments.cancel', [$sequence, $enrollment]) }}">
                                            @csrf
                                            <button type="submit" class="text-red-600 text-sm hover:underline">Cancelar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">No hay contactos inscritos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $enrollments->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
