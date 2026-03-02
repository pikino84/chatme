<div class="fixed inset-0 z-50 flex justify-end">
    <a href="{{ route('deals.board', ['pipeline_id' => $deal->pipeline_id]) }}" class="bg-black/30 absolute inset-0"></a>
    <div class="relative w-[480px] bg-white dark:bg-gray-800 shadow-xl overflow-y-auto">
        {{-- Header --}}
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 truncate">{{ $deal->contact_name }}</h3>
            <a href="{{ route('deals.board', ['pipeline_id' => $deal->pipeline_id]) }}"
               class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-xl">&times;</a>
        </div>

        <div class="p-4 space-y-6">
            {{-- Contact Info --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Contact</h4>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Name</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $deal->contact_name }}</dd>
                    </div>
                    @if($deal->contact_email)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $deal->contact_email }}</dd>
                        </div>
                    @endif
                    @if($deal->contact_phone)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $deal->contact_phone }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Deal Info --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Deal</h4>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Pipeline</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $deal->pipeline->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Stage</dt>
                        <dd>
                            <span class="inline-block w-2 h-2 rounded-full mr-1" style="background: {{ $deal->stage->color ?? '#6B7280' }}"></span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $deal->stage->name }}</span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Value</dt>
                        <dd class="text-gray-900 dark:text-gray-100">${{ number_format($deal->value, 2) }} {{ $deal->currency }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="text-gray-900 dark:text-gray-100 capitalize">{{ $deal->status }}</dd>
                    </div>
                    @if($deal->expected_close_date)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Expected Close</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $deal->expected_close_date->format('M d, Y') }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Assigned to</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $deal->assignedUser?->name ?? 'Unassigned' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Move Stage --}}
            @can('update', $deal)
                <div>
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Move Stage</h4>
                    <form method="POST" action="{{ route('deals.move', $deal) }}" class="flex gap-2">
                        @csrf
                        <select name="pipeline_stage_id"
                                class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach($dealStages as $s)
                                <option value="{{ $s->id }}" @selected($s->id === $deal->pipeline_stage_id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Move</button>
                    </form>
                </div>
            @endcan

            {{-- Assign --}}
            @can('assign', $deal)
                <div>
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Reassign</h4>
                    <form method="POST" action="{{ route('deals.assign', $deal) }}" class="flex gap-2">
                        @csrf
                        <select name="assigned_user_id"
                                class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach($agents as $a)
                                <option value="{{ $a->id }}" @selected($deal->assigned_user_id === $a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Assign</button>
                    </form>
                </div>
            @endcan

            {{-- Notes --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Notes</h4>

                @can('create', App\Models\DealNote::class)
                    <form method="POST" action="{{ route('deals.notes.store', $deal) }}" class="mb-3">
                        @csrf
                        <textarea name="body" rows="2" required placeholder="Add a note..."
                                  class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"></textarea>
                        <button type="submit" class="mt-1 px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">Add Note</button>
                    </form>
                @endcan

                <div class="space-y-2">
                    @forelse($deal->notes as $note)
                        <div class="bg-gray-50 dark:bg-gray-700 rounded p-2">
                            <div class="flex justify-between text-xs text-gray-400 mb-1">
                                <span>{{ $note->user->name }}</span>
                                <span>{{ $note->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-gray-800 dark:text-gray-200">{{ $note->body }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">No notes yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Stage History --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Stage History</h4>
                <div class="space-y-2">
                    @forelse($deal->stageHistory as $history)
                        <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <span>{{ $history->fromStage?->name ?? 'Created' }}</span>
                            <span>&rarr;</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $history->toStage->name }}</span>
                            @if($history->changedByUser)
                                <span class="ml-1">by {{ $history->changedByUser->name }}</span>
                            @endif
                            <span class="ml-auto">{{ $history->changed_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">No history.</p>
                    @endforelse
                </div>
            </div>

            {{-- Linked Conversation --}}
            @if($deal->conversation)
                <div>
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Linked Conversation</h4>
                    <a href="{{ route('inbox.conversations.show', $deal->conversation) }}"
                       class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        {{ $deal->conversation->contact_name ?? $deal->conversation->contact_identifier }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
