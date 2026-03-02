<x-app-layout>
    <div class="max-w-4xl mx-auto py-8 px-4">
        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $article->title }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $article->category?->name ?? 'Uncategorized' }}
                        &middot; by {{ $article->creator?->name ?? 'Unknown' }}
                        &middot; {{ $article->updated_at?->format('M d, Y') }}
                    </p>
                </div>
                @if($article->status === 'published')
                    <span class="text-xs px-2.5 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">Published</span>
                @elseif($article->status === 'archived')
                    <span class="text-xs px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Archived</span>
                @else
                    <span class="text-xs px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">Draft</span>
                @endif
            </div>

            <div class="prose dark:prose-invert max-w-none text-sm text-gray-800 dark:text-gray-200 mb-6 whitespace-pre-wrap">{{ $article->content }}</div>

            <div class="flex flex-wrap gap-2 mb-4">
                @foreach(['webchat', 'whatsapp', 'instagram', 'facebook'] as $channel)
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $article->isVisibleOn($channel) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 line-through' }}">
                        {{ ucfirst($channel) }}
                    </span>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2 border-t border-gray-200 dark:border-gray-700 pt-4">
                @can('update', $article)
                    <a href="{{ route('kb.articles.edit', $article) }}"
                       class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">Edit</a>
                @endcan

                @if($article->isDraft())
                    @can('publish', $article)
                        <form action="{{ route('kb.articles.publish', $article) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 transition">Publish</button>
                        </form>
                    @endcan
                @endif

                @if($article->isPublished())
                    @can('update', $article)
                        <form action="{{ route('kb.articles.archive', $article) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">Archive</button>
                        </form>
                    @endcan
                @endif

                @can('delete', $article)
                    <form action="{{ route('kb.articles.destroy', $article) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 text-sm bg-red-600 text-white rounded-md hover:bg-red-700 transition">Delete</button>
                    </form>
                @endcan

                <a href="{{ route('kb.articles') }}" class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:underline">&larr; Back</a>
            </div>
        </div>

        @if($article->versions->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase mb-3">Version History</h3>
                <table class="w-full text-sm">
                    <thead class="text-left">
                        <tr>
                            <th class="px-3 py-2 text-gray-500 dark:text-gray-400 font-medium">Version</th>
                            <th class="px-3 py-2 text-gray-500 dark:text-gray-400 font-medium">Title</th>
                            <th class="px-3 py-2 text-gray-500 dark:text-gray-400 font-medium">Changed By</th>
                            <th class="px-3 py-2 text-gray-500 dark:text-gray-400 font-medium">Summary</th>
                            <th class="px-3 py-2 text-gray-500 dark:text-gray-400 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($article->versions as $version)
                            <tr>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">v{{ $version->version_number }}</td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($version->title, 40) }}</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $version->changedByUser?->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $version->change_summary ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $version->created_at?->format('M d, Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
