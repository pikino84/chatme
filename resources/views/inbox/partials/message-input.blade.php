<div class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3">
    <form method="POST" action="{{ route('inbox.conversations.messages.store', $conversation) }}" class="flex gap-2" x-data="{ type: 'text' }">
        @csrf
        <input type="hidden" name="type" :value="type">

        <div class="flex-1 relative">
            <textarea name="body" rows="1" required
                      placeholder="Escribe un mensaje..."
                      class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500 resize-none pr-10"
                      @keydown.enter.prevent="if (!$event.shiftKey) $el.closest('form').submit()"></textarea>
        </div>

        <div class="flex flex-col gap-1">
            <button type="submit" @click="type = 'text'"
                    class="px-3 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">
                Enviar
            </button>
            @can('sendInternalNote', [App\Models\Message::class, $conversation])
            <button type="submit" @click="type = 'internal_note'"
                    class="px-3 py-2 text-xs font-medium text-yellow-700 bg-yellow-100 hover:bg-yellow-200 dark:text-yellow-300 dark:bg-yellow-900/30 dark:hover:bg-yellow-900/50 rounded-lg">
                Nota
            </button>
            @endcan
        </div>
    </form>
</div>
