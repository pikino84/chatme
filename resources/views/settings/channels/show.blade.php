<x-app-layout>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $channel->name }}</h2>
            <a href="{{ route('settings.channels') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; All Channels</a>
        </div>

        @if(session('success'))
            <div class="px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm rounded">
                {{ session('error') }}
            </div>
        @endif

        {{-- Channel Info --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Channel Details</h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Type</dt>
                    <dd class="mt-1">
                        @if($channel->type === 'whatsapp')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">WhatsApp</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">Webchat</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1">
                        @if($channel->is_active)
                            <span class="text-green-600 dark:text-green-400 font-medium">Active</span>
                        @else
                            <span class="text-gray-400 font-medium">Inactive</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $channel->created_at->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Conversations</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $channel->conversations_count }}</dd>
                </div>
            </dl>
        </div>

        {{-- WhatsApp Integration --}}
        @if($channel->type === 'whatsapp')
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6" x-data>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">WhatsApp Integration</h3>

                @php $config = $channel->configuration ?? []; @endphp

                <div class="space-y-4 text-sm">
                    <div>
                        <label class="block text-gray-500 dark:text-gray-400 mb-1">Webhook URL</label>
                        <div class="flex gap-2">
                            <input type="text" value="{{ $webhookUrl }}" readonly
                                   class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm bg-gray-50 dark:bg-gray-800">
                            <button type="button"
                                    x-on:click="navigator.clipboard.writeText('{{ $webhookUrl }}'); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy', 2000)"
                                    class="px-3 py-2 text-xs bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 transition">
                                Copy
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-gray-500 dark:text-gray-400 mb-1">Verify Token</label>
                        <code class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ $config['verify_token'] ?? '—' }}</code>
                    </div>

                    <div>
                        <label class="block text-gray-500 dark:text-gray-400 mb-1">Display Phone</label>
                        <span class="text-gray-900 dark:text-gray-100">{{ $config['display_phone'] ?? '—' }}</span>
                    </div>

                    <div>
                        <label class="block text-gray-500 dark:text-gray-400 mb-1">Credentials</label>
                        <span class="text-gray-500 dark:text-gray-400">
                            Access Token: ********{{ !empty($config['access_token']) ? substr($config['access_token'], -8) : '—' }}<br>
                            App Secret: ********{{ !empty($config['app_secret']) ? substr($config['app_secret'], -8) : '—' }}
                        </span>
                    </div>

                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs text-gray-400">
                            Configure this Webhook URL and Verify Token in your
                            <a href="https://developers.facebook.com/" target="_blank" class="text-indigo-500 hover:underline">Meta Developer Console</a>
                            under your WhatsApp Business App settings.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Webchat Integration --}}
        @if($channel->type === 'webchat')
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6" x-data>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Webchat Integration</h3>

                @php $config = $channel->configuration ?? []; @endphp

                <div class="space-y-4 text-sm">
                    <div>
                        <label class="block text-gray-500 dark:text-gray-400 mb-1">Embed Snippet</label>
                        <div class="relative">
                            <pre class="bg-gray-100 dark:bg-gray-700 rounded-md p-3 text-xs overflow-x-auto"><code>{{ $widgetSnippet }}</code></pre>
                            <button type="button"
                                    x-on:click="navigator.clipboard.writeText(@js($widgetSnippet)); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy', 2000)"
                                    class="absolute top-2 right-2 px-2 py-1 text-xs bg-white dark:bg-gray-600 text-gray-600 dark:text-gray-200 rounded border border-gray-200 dark:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                                Copy
                            </button>
                        </div>
                    </div>

                    @if(!empty($config['allowed_origins']))
                        <div>
                            <label class="block text-gray-500 dark:text-gray-400 mb-1">Allowed Origins</label>
                            <ul class="list-disc list-inside text-gray-700 dark:text-gray-300">
                                @foreach($config['allowed_origins'] as $origin)
                                    <li>{{ $origin }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Form Template --}}
                    <div class="pt-3 border-t border-gray-100 dark:border-gray-700">
                        <label class="block text-gray-500 dark:text-gray-400 mb-2">Form Template</label>
                        @can('channels.manage')
                            <form method="POST" action="{{ route('settings.channels.update', $channel) }}" class="flex items-end gap-2">
                                @csrf
                                <input type="hidden" name="name" value="{{ $channel->name }}">
                                <select name="template_key"
                                        class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                    <option value="">— None —</option>
                                    @foreach($formTemplates as $key => $tpl)
                                        <option value="{{ $key }}" @selected(optional($channel->form)->template_key === $key)>
                                            {{ $tpl['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit"
                                        class="px-3 py-2 text-xs bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                                    Save
                                </button>
                            </form>
                            @if($channel->form)
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                    Current: {{ $formTemplates[$channel->form->template_key]['name'] ?? $channel->form->template_key }}
                                </p>
                            @endif
                        @else
                            <p class="text-gray-700 dark:text-gray-300">
                                {{ $channel->form ? ($formTemplates[$channel->form->template_key]['name'] ?? $channel->form->template_key) : 'None' }}
                            </p>
                        @endcan
                    </div>
                </div>
            </div>
        @endif

        {{-- Actions --}}
        @can('channels.manage')
            <div class="flex items-center gap-3">
                <a href="{{ route('settings.channels.edit', $channel) }}"
                   class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    Edit
                </a>
                <form method="POST" action="{{ route('settings.channels.toggle', $channel) }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 text-sm rounded-md transition {{ $channel->is_active ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300' : 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300' }}">
                        {{ $channel->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('settings.channels.delete', $channel) }}"
                      onsubmit="return confirm('Are you sure you want to delete this channel?')">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-red-100 text-red-800 rounded-md hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 transition">
                        Delete
                    </button>
                </form>
            </div>
        @endcan
    </div>
</x-app-layout>
