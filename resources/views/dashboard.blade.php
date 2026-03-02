<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Dashboard
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Module Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

                {{-- Inbox --}}
                <a href="{{ route('inbox') }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition p-6">
                    <div class="text-3xl mb-3">&#9993;</div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Inbox</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">View and manage conversations.</p>
                </a>

                {{-- Deals --}}
                <a href="{{ route('deals.board') }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition p-6">
                    <div class="text-3xl mb-3">&#9776;</div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Deals</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">CRM pipeline and deal board.</p>
                </a>

                {{-- Knowledge Base --}}
                <a href="{{ route('kb.articles') }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition p-6">
                    <div class="text-3xl mb-3">&#128218;</div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Knowledge Base</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Articles, categories, and AI config.</p>
                </a>

                {{-- Billing --}}
                @can('settings.manage')
                <a href="{{ route('billing.show') }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition p-6">
                    <div class="text-3xl mb-3">&#128179;</div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Billing</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Subscription, plans, and usage.</p>
                </a>
                @endcan

                {{-- Settings --}}
                @can('settings.manage')
                <a href="{{ route('settings.show') }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition p-6">
                    <div class="text-3xl mb-3">&#9881;</div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Settings</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Organization, team, and AI config.</p>
                </a>
                @endcan

            </div>
        </div>
    </div>
</x-app-layout>
