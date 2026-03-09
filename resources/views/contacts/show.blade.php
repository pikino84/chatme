<x-app-layout>
    <x-slot name="header">{{ $contact->name }}</x-slot>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Contact info --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Informacion</h3>
                <div class="flex gap-2">
                    @can('update', $contact)
                        <a href="{{ route('contacts.edit', $contact) }}" class="text-xs text-indigo-600 hover:text-indigo-800">Editar</a>
                    @endcan
                    @can('delete', $contact)
                        <form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm('Eliminar contacto?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:text-red-800">Eliminar</button>
                        </form>
                    @endcan
                </div>
            </div>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                    <dd class="text-gray-900 dark:text-gray-200">{{ $contact->email ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Telefono</dt>
                    <dd class="text-gray-900 dark:text-gray-200">{{ $contact->phone ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Empresa</dt>
                    <dd class="text-gray-900 dark:text-gray-200">{{ $contact->company ?? '-' }}</dd>
                </div>
                @if($contact->notes)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Notas</dt>
                        <dd class="text-gray-900 dark:text-gray-200 whitespace-pre-line">{{ $contact->notes }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Creado</dt>
                    <dd class="text-gray-900 dark:text-gray-200">{{ $contact->created_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Conversations --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Conversaciones ({{ $contact->conversations->count() }})</h3>
            <div class="space-y-2">
                @forelse($contact->conversations as $conv)
                    <a href="{{ route('inbox.conversations.show', $conv) }}" class="block p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-750 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-200">{{ $conv->subject ?? 'Sin asunto' }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $conv->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($conv->status) }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ $conv->channel->type ?? '' }} &middot; {{ $conv->created_at->format('d/m/Y') }}</div>
                    </a>
                @empty
                    <p class="text-sm text-gray-500">Sin conversaciones</p>
                @endforelse
            </div>
        </div>

        {{-- Deals --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Negocios ({{ $contact->deals->count() }})</h3>
            <div class="space-y-2">
                @forelse($contact->deals as $deal)
                    <a href="{{ route('deals.show', $deal) }}" class="block p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-750 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-200">{{ $deal->contact_name ?? $contact->name }}</span>
                            <span class="text-xs font-semibold {{ $deal->status === 'won' ? 'text-green-600' : ($deal->status === 'lost' ? 'text-red-600' : 'text-indigo-600') }}">${{ number_format($deal->value, 2) }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ $deal->pipeline->name ?? '' }} &middot; {{ $deal->stage->name ?? '' }} &middot; {{ ucfirst($deal->status) }}</div>
                    </a>
                @empty
                    <p class="text-sm text-gray-500">Sin negocios</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
