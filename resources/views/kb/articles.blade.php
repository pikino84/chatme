<x-app-layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">KB Articles</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('kb.categories') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                    Categories
                </a>
                @can('create', App\Models\KbArticle::class)
                    <a href="{{ route('kb.articles.create') }}"
                       class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                        + New Article
                    </a>
                @endcan
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex gap-3 mb-4">
            <form method="GET" action="{{ route('kb.articles') }}" class="flex gap-3">
                <select name="category_id" onchange="this.form.submit()"
                        class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                <select name="status" onchange="this.form.submit()"
                        class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    <option value="">All Statuses</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="published" @selected(request('status') === 'published')>Published</option>
                    <option value="archived" @selected(request('status') === 'archived')>Archived</option>
                </select>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-left">
                    <tr>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Title</th>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Category</th>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium text-center">Status</th>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Creator</th>
                        <th class="px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($articles as $article)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('kb.articles.show', $article) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $article->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $article->category?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($article->status === 'published')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">Published</span>
                                @elseif($article->status === 'archived')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Archived</span>
                                @else
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">Draft</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $article->creator?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $article->updated_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No articles yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $articles->withQueryString()->links() }}
        </div>
    </div>
</x-app-layout>
