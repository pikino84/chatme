<x-app-layout>
    <div class="max-w-4xl mx-auto py-10 px-4">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-8">Tenant Dashboard</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            {{-- Inbox --}}
            <a href="{{ route('tenant.inbox') }}"
               class="block bg-white dark:bg-gray-800 rounded-lg shadow p-6 hover:ring-2 hover:ring-indigo-500 transition">
                <div class="text-2xl mb-2">&#9993;</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Inbox</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">View and manage conversations.</p>
            </a>

            {{-- Kanban --}}
            <a href="{{ route('tenant.kanban') }}"
               class="block bg-white dark:bg-gray-800 rounded-lg shadow p-6 hover:ring-2 hover:ring-indigo-500 transition">
                <div class="text-2xl mb-2">&#9776;</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Deals</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">CRM pipeline and deal board.</p>
            </a>

            {{-- Settings --}}
            <a href="{{ route('tenant.settings') }}"
               class="block bg-white dark:bg-gray-800 rounded-lg shadow p-6 hover:ring-2 hover:ring-indigo-500 transition">
                <div class="text-2xl mb-2">&#9881;</div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Settings</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Organization, team, and AI config.</p>
            </a>
        </div>
    </div>
</x-app-layout>
