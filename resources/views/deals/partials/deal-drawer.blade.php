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
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Contacto</h4>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Nombre</dt>
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
                            <dt class="text-gray-500 dark:text-gray-400">Telefono</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $deal->contact_phone }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Deal Info --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Negocio</h4>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Pipeline</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $deal->pipeline->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Etapa</dt>
                        <dd>
                            <span class="inline-block w-2 h-2 rounded-full mr-1" style="background: {{ $deal->stage->color ?? '#6B7280' }}"></span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $deal->stage->name }}</span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Valor</dt>
                        <dd class="text-gray-900 dark:text-gray-100">${{ number_format($deal->value, 2) }} {{ $deal->currency }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Estado</dt>
                        <dd class="text-gray-900 dark:text-gray-100 capitalize">{{ $deal->status }}</dd>
                    </div>
                    @if($deal->expected_close_date)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Cierre Esperado</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $deal->expected_close_date->format('M d, Y') }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Asignado a</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $deal->assignedUser?->name ?? 'Sin asignar' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Tags --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Etiquetas</h4>
                @if($deal->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach($deal->tags as $tag)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300">
                                <span class="inline-block w-2 h-2 rounded-full" style="background: {{ $tag->color }}"></span>
                                {{ $tag->name }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @can('update', $deal)
                    @if(isset($allTags) && $allTags->count() > 0)
                        <form method="POST" action="{{ route('deals.tags.sync', $deal) }}" class="mt-1">
                            @csrf
                            <div class="flex flex-wrap gap-2 mb-2">
                                @foreach($allTags as $tag)
                                    <label class="inline-flex items-center gap-1 text-xs cursor-pointer">
                                        <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}"
                                               @checked($deal->tags->contains($tag->id))
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="inline-block w-2 h-2 rounded-full" style="background: {{ $tag->color }}"></span>
                                        <span class="text-gray-600 dark:text-gray-400">{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="px-2 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">Actualizar Etiquetas</button>
                        </form>
                    @else
                        <p class="text-xs text-gray-400">No hay etiquetas disponibles. <a href="{{ route('tags.index') }}" class="text-indigo-500 hover:underline">Crear etiquetas</a></p>
                    @endif
                @endcan
            </div>

            {{-- Move Stage --}}
            @can('update', $deal)
                <div>
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Mover Etapa</h4>
                    <form method="POST" action="{{ route('deals.move', $deal) }}" class="flex gap-2">
                        @csrf
                        <select name="pipeline_stage_id"
                                class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach($dealStages as $s)
                                <option value="{{ $s->id }}" @selected($s->id === $deal->pipeline_stage_id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Mover</button>
                    </form>
                </div>
            @endcan

            {{-- Assign --}}
            @can('assign', $deal)
                <div>
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Reasignar</h4>
                    <form method="POST" action="{{ route('deals.assign', $deal) }}" class="flex gap-2">
                        @csrf
                        <select name="assigned_user_id"
                                class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach($agents as $a)
                                <option value="{{ $a->id }}" @selected($deal->assigned_user_id === $a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Asignar</button>
                    </form>
                </div>
            @endcan

            {{-- Attachments --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Archivos Adjuntos</h4>

                @can('update', $deal)
                    <form method="POST" action="{{ route('deals.attachments.store', $deal) }}" enctype="multipart/form-data" class="mb-3">
                        @csrf
                        <div class="flex items-center gap-2">
                            <input type="file" name="file" required
                                   class="flex-1 text-sm text-gray-500 dark:text-gray-400 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-300 hover:file:bg-gray-200 dark:hover:file:bg-gray-600">
                            <button type="submit" class="px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">Subir</button>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Max 10 MB</p>
                    </form>
                @endcan

                <div class="space-y-2">
                    @forelse($deal->attachments as $attachment)
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 rounded p-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <div class="min-w-0">
                                    <a href="{{ $attachment->url() }}" target="_blank" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline truncate block">{{ $attachment->file_name }}</a>
                                    <p class="text-xs text-gray-400">{{ $attachment->sizeForHumans() }} &middot; {{ $attachment->user->name }} &middot; {{ $attachment->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            @can('update', $deal)
                                <form method="POST" action="{{ route('deals.attachments.destroy', [$deal, $attachment]) }}" onsubmit="return confirm('¿Eliminar archivo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">Sin archivos adjuntos.</p>
                    @endforelse
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Notas</h4>

                @can('create', App\Models\DealNote::class)
                    <form method="POST" action="{{ route('deals.notes.store', $deal) }}" class="mb-3">
                        @csrf
                        <textarea name="body" rows="2" required placeholder="Agregar nota..."
                                  class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"></textarea>
                        <button type="submit" class="mt-1 px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">Agregar Nota</button>
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
                        <p class="text-xs text-gray-400">Sin notas aun.</p>
                    @endforelse
                </div>
            </div>

            {{-- Commissions --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Comisiones</h4>

                @if(auth()->user()->hasPermissionTo('deals.manage-commissions'))
                    <form method="POST" action="{{ route('deals.commissions.store', $deal) }}" class="mb-3 bg-gray-50 dark:bg-gray-700/50 rounded p-2">
                        @csrf
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <select name="user_id" required
                                    class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                <option value="">Agente...</option>
                                @foreach($agents as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                            <div class="flex items-center gap-1">
                                <input type="number" name="percentage" step="0.01" min="0" max="100" required placeholder="%"
                                       class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                <span class="text-xs text-gray-500">%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-400">Valor del negocio: ${{ number_format($deal->value, 2) }}</span>
                            <button type="submit" class="px-2 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">Agregar</button>
                        </div>
                    </form>
                @endif

                <div class="space-y-2">
                    @forelse($deal->commissions as $commission)
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 rounded p-2">
                            <div>
                                <p class="text-sm text-gray-900 dark:text-gray-100">{{ $commission->user->name }}</p>
                                <p class="text-xs text-gray-400">{{ $commission->percentage }}% &middot; ${{ number_format($commission->amount, 2) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
                                        'approved' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                                        'paid' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
                                        'cancelled' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
                                    ];
                                    $statusLabels = [
                                        'pending' => 'Pendiente',
                                        'approved' => 'Aprobada',
                                        'paid' => 'Pagada',
                                        'cancelled' => 'Cancelada',
                                    ];
                                @endphp

                                @if(auth()->user()->hasPermissionTo('deals.manage-commissions'))
                                    <form method="POST" action="{{ route('deals.commissions.status', [$deal, $commission]) }}" class="inline">
                                        @csrf
                                        <select name="status" onchange="this.form.submit()"
                                                class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 py-1">
                                            @foreach($statusLabels as $val => $label)
                                                <option value="{{ $val }}" @selected($commission->status === $val)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    <form method="POST" action="{{ route('deals.commissions.destroy', [$deal, $commission]) }}" class="inline" onsubmit="return confirm('¿Eliminar comision?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 dark:text-red-400">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </form>
                                @else
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $statusColors[$commission->status] ?? '' }}">
                                        {{ $statusLabels[$commission->status] ?? $commission->status }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">Sin comisiones.</p>
                    @endforelse
                </div>
            </div>

            {{-- Stage History --}}
            <div>
                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Historial de Etapas</h4>
                <div class="space-y-2">
                    @forelse($deal->stageHistory as $history)
                        <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <span>{{ $history->fromStage?->name ?? 'Creado' }}</span>
                            <span>&rarr;</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $history->toStage->name }}</span>
                            @if($history->changedByUser)
                                <span class="ml-1">by {{ $history->changedByUser->name }}</span>
                            @endif
                            <span class="ml-auto">{{ $history->changed_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">Sin historial.</p>
                    @endforelse
                </div>
            </div>

            {{-- Linked Conversation --}}
            @if($deal->conversation)
                <div>
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Conversacion Vinculada</h4>
                    <a href="{{ route('inbox.conversations.show', $deal->conversation) }}"
                       class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        {{ $deal->conversation->contact_name ?? $deal->conversation->contact_identifier }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
