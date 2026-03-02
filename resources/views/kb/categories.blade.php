<x-app-layout>
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">KB Categories</h2>
            <a href="{{ route('kb.articles') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                Articles &rarr;
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        @can('kb.create')
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">New Category</h3>
                <form action="{{ route('kb.categories.store') }}" method="POST" class="flex flex-wrap gap-3 items-end">
                    @csrf
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Name</label>
                        <input type="text" name="name" required
                               class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Description</label>
                        <input type="text" name="description"
                               class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    </div>
                    <div class="w-20">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Position</label>
                        <input type="number" name="position" value="0" min="0"
                               class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    </div>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                        Add
                    </button>
                </form>
            </div>
        @endcan

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                    <tr>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Name</th>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Description</th>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium text-center">Position</th>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium text-center">Articles</th>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium text-center">Status</th>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($categories as $cat)
                        <tr x-data="{ editing: false }">
                            <template x-if="!editing">
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100" colspan="1">{{ $cat->name }}</td>
                            </template>
                            <template x-if="!editing">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $cat->description }}</td>
                            </template>
                            <template x-if="!editing">
                                <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ $cat->position }}</td>
                            </template>
                            <template x-if="!editing">
                                <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ $cat->articles_count }}</td>
                            </template>
                            <template x-if="!editing">
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $cat->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                        {{ $cat->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                            </template>
                            <template x-if="!editing">
                                <td class="px-4 py-3 text-right space-x-2">
                                    @can('update', $cat)
                                        <button @click="editing = true" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                                    @endcan
                                    @can('delete', $cat)
                                        <form action="{{ route('kb.categories.destroy', $cat) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </template>

                            <template x-if="editing">
                                <td colspan="6" class="px-4 py-3">
                                    <form action="{{ route('kb.categories.update', $cat) }}" method="POST" class="flex flex-wrap gap-3 items-end">
                                        @csrf
                                        <div class="flex-1 min-w-[150px]">
                                            <input type="text" name="name" value="{{ $cat->name }}" required
                                                   class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                        </div>
                                        <div class="flex-1 min-w-[150px]">
                                            <input type="text" name="description" value="{{ $cat->description }}"
                                                   class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                        </div>
                                        <div class="w-20">
                                            <input type="number" name="position" value="{{ $cat->position }}" min="0"
                                                   class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                        </div>
                                        <label class="flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="checkbox" name="is_active" value="1" @checked($cat->is_active)>
                                            Active
                                        </label>
                                        <button type="submit"
                                                class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                                            Save
                                        </button>
                                        <button type="button" @click="editing = false"
                                                class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:underline">
                                            Cancel
                                        </button>
                                    </form>
                                </td>
                            </template>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No categories yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
