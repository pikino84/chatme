<div class="space-y-4 p-4 text-sm">
    {{-- Contact Info --}}
    <div>
        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Contact</h4>
        <dl class="space-y-1 text-xs">
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Name</dt>
                <dd class="text-gray-900 dark:text-gray-200">{{ $conversation->contact_name ?: '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Identifier</dt>
                <dd class="text-gray-900 dark:text-gray-200 font-mono">{{ $conversation->contact_identifier }}</dd>
            </div>
        </dl>
    </div>

    {{-- Conversation Details --}}
    <div>
        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Details</h4>
        <dl class="space-y-1 text-xs">
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                <dd>
                    <span class="px-1.5 py-0.5 rounded text-xs font-medium
                        @if($conversation->status === 'open') bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300
                        @elseif($conversation->status === 'pending') bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300
                        @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300
                        @endif">
                        {{ ucfirst($conversation->status) }}
                    </span>
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Priority</dt>
                <dd class="text-gray-900 dark:text-gray-200">{{ ucfirst($conversation->priority) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Channel</dt>
                <dd class="text-gray-900 dark:text-gray-200">{{ $conversation->channel->name }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Agent</dt>
                <dd class="text-gray-900 dark:text-gray-200">{{ $conversation->assignedUser?->name ?? 'Unassigned' }}</dd>
            </div>
            @if($conversation->branch)
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Branch</dt>
                <dd class="text-gray-900 dark:text-gray-200">{{ $conversation->branch->name }}</dd>
            </div>
            @endif
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                <dd class="text-gray-900 dark:text-gray-200">{{ $conversation->created_at->format('M d, Y H:i') }}</dd>
            </div>
        </dl>
    </div>

    {{-- Actions --}}
    <div>
        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Actions</h4>
        <div class="space-y-2">
            @if($conversation->isOpen())
                @can('close', $conversation)
                <form method="POST" action="{{ route('inbox.conversations.close', $conversation) }}">
                    @csrf
                    <button type="submit" class="w-full text-xs px-3 py-1.5 rounded bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 dark:hover:bg-red-900/50">
                        Close Conversation
                    </button>
                </form>
                @endcan
            @else
                @can('reopen', $conversation)
                <form method="POST" action="{{ route('inbox.conversations.reopen', $conversation) }}">
                    @csrf
                    <button type="submit" class="w-full text-xs px-3 py-1.5 rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-900/50">
                        Reopen Conversation
                    </button>
                </form>
                @endcan
            @endif

            @can('assign', $conversation)
            <form method="POST" action="{{ route('inbox.conversations.assign', $conversation) }}" class="flex gap-1">
                @csrf
                <select name="assigned_user_id" class="flex-1 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" @selected($conversation->assigned_user_id === $agent->id)>{{ $agent->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300">
                    Assign
                </button>
            </form>
            @endcan

            @can('transfer', $conversation)
            <form method="POST" action="{{ route('inbox.conversations.transfer', $conversation) }}" x-data="{ show: false }">
                @csrf
                <button type="button" @click="show = !show" class="w-full text-xs px-3 py-1.5 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50 mb-1">
                    Transfer
                </button>
                <div x-show="show" x-cloak class="space-y-1">
                    <select name="to_user_id" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        @foreach($agents as $agent)
                            @if($agent->id !== $conversation->assigned_user_id)
                                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <input type="text" name="reason" placeholder="Reason (optional)" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    <button type="submit" class="w-full text-xs px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">
                        Confirm Transfer
                    </button>
                </div>
            </form>
            @endcan
        </div>
    </div>

    {{-- Metadata --}}
    @if($conversation->metadata)
    <div>
        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Metadata</h4>
        <dl class="space-y-1 text-xs">
            @foreach($conversation->metadata as $key => $value)
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                    <dd class="text-gray-900 dark:text-gray-200">{{ is_array($value) ? json_encode($value) : $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
    @endif
</div>
