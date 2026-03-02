<x-app-layout>
    <div class="max-w-3xl mx-auto py-8 px-4">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
            {{ $article ? 'Edit Article' : 'New Article' }}
        </h2>

        @if($errors->any())
            <div class="mb-4 px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ $article ? route('kb.articles.update', $article) : route('kb.articles.store') }}"
              method="POST"
              class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title', $article?->title) }}" required
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                <select name="kb_category_id" required
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    <option value="">Select category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('kb_category_id', $article?->kb_category_id) == $cat->id)>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Content <span class="text-xs text-gray-400">(plain text)</span>
                </label>
                <textarea name="content" rows="12" required
                          class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">{{ old('content', $article?->content) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                <input type="number" name="priority" value="{{ old('priority', $article?->priority ?? 0) }}" min="0" max="100"
                       class="w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            </div>

            @if($article)
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Change Summary</label>
                    <input type="text" name="change_summary" value="{{ old('change_summary') }}" placeholder="What changed?"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
            @endif

            <fieldset class="border border-gray-200 dark:border-gray-700 rounded-md p-4">
                <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-2">Channel Visibility</legend>
                <div class="flex flex-wrap gap-4 mt-2">
                    @foreach(['webchat', 'whatsapp', 'instagram', 'facebook'] as $channel)
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="visible_on_{{ $channel }}" value="1"
                                   @checked(old("visible_on_{$channel}", $article?->{"visible_on_{$channel}"}))>
                            {{ ucfirst($channel) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    {{ $article ? 'Update Article' : 'Create Article' }}
                </button>
                <a href="{{ route('kb.articles') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
