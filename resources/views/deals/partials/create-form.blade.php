<div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="bg-black/30 absolute inset-0" @click="showCreateModal = false"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">New Deal</h3>

        <form method="POST" action="{{ route('deals.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="pipeline_id" value="{{ $activePipeline->id }}">

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Name *</label>
                <input type="text" name="contact_name" required
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                <input type="email" name="contact_email"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                <input type="text" name="contact_phone"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
            </div>

            <div class="flex gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Value</label>
                    <input type="number" name="value" step="0.01" min="0"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expected Close</label>
                    <input type="date" name="expected_close_date"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign to</label>
                <select name="assigned_user_id"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    <option value="">Unassigned</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="showCreateModal = false"
                        class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    Cancel
                </button>
                <button type="submit"
                        class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    Create Deal
                </button>
            </div>
        </form>
    </div>
</div>
