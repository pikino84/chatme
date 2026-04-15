<x-app-layout>
    <div x-data="{ showCreateModal: false }" class="flex flex-col sm:-m-6" style="height: calc(100vh - 64px);">
        {{-- Header --}}
        <div class="px-4 py-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Negocios</h2>

                @if($pipelines->count() > 1)
                    <select onchange="window.location.href='{{ route('deals.board') }}?pipeline_id=' + this.value"
                            class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        @foreach($pipelines as $p)
                            <option value="{{ $p->id }}" @selected($activePipeline && $p->id === $activePipeline->id)>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                @elseif($activePipeline)
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $activePipeline->name }}</span>
                @endif
            </div>

            @can('create', App\Models\Deal::class)
                <button @click="showCreateModal = true"
                        class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    + Nuevo Negocio
                </button>
            @endcan
        </div>

        @if($activePipeline)
            {{-- Board --}}
            <div class="flex overflow-x-auto flex-1 min-h-0 p-4 gap-4 bg-gray-100 dark:bg-gray-900">
                @foreach($stages as $stage)
                    @include('deals.partials.stage-column', ['stage' => $stage, 'allStages' => $stages])
                @endforeach
            </div>
        @else
            <div class="flex items-center justify-center flex-1 min-h-0 bg-gray-100 dark:bg-gray-900">
                <p class="text-gray-500 dark:text-gray-400">No hay pipelines configurados.</p>
            </div>
        @endif

        {{-- Create deal modal --}}
        @can('create', App\Models\Deal::class)
            @if($activePipeline)
                @include('deals.partials.create-form', ['activePipeline' => $activePipeline, 'agents' => $agents])
            @endif
        @endcan

        {{-- AJAX Deal Drawer --}}
        <div id="deal-drawer-overlay" class="fixed inset-0 z-50 flex justify-end hidden">
            <div class="bg-black/30 absolute inset-0" onclick="closeDealDrawer()"></div>
            <div id="deal-drawer-panel" class="relative w-[480px] bg-white dark:bg-gray-800 shadow-xl overflow-y-auto transform translate-x-full transition-transform duration-300">
                <div id="deal-drawer-content" class=""></div>
            </div>
        </div>

        {{-- Legacy drawer for direct URL access --}}
        @isset($selectedDeal)
            @include('deals.partials.deal-drawer', ['deal' => $selectedDeal, 'dealStages' => $dealStages, 'agents' => $agents, 'allTags' => $allTags ?? collect()])
        @endisset
    </div>

    @push('scripts')
    <style>
    .drop-zone.drag-over {
        background: rgba(99, 102, 241, 0.1);
        border: 2px dashed #6366f1;
        border-radius: 0.5rem;
    }
    .deal-card.dragging {
        opacity: 0.4;
        transform: rotate(2deg);
    }
    </style>
    <script>
    var csrfToken = '{{ csrf_token() }}';
    var draggedCard = null;
    var draggedDealId = null;
    var sourceStageId = null;

    // ── Drag & Drop ──
    function handleDragStart(e) {
        draggedCard = e.currentTarget;
        draggedDealId = draggedCard.dataset.dealId;
        sourceStageId = draggedCard.dataset.stageId;
        draggedCard.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', draggedDealId);
    }

    function handleDragEnd(e) {
        if (draggedCard) draggedCard.classList.remove('dragging');
        document.querySelectorAll('.drop-zone').forEach(function(z) {
            z.classList.remove('drag-over');
        });
        draggedCard = null;
        draggedDealId = null;
        sourceStageId = null;
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    function handleDragEnter(e) {
        e.preventDefault();
        var zone = e.currentTarget;
        zone.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        var zone = e.currentTarget;
        // Only remove if we actually left the zone (not entering a child)
        if (!zone.contains(e.relatedTarget)) {
            zone.classList.remove('drag-over');
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        var zone = e.currentTarget;
        zone.classList.remove('drag-over');

        var targetStageId = zone.dataset.stageId;
        if (!draggedCard || !draggedDealId) return;
        if (targetStageId === sourceStageId) return;

        // Move card in DOM immediately
        var emptyMsg = zone.querySelector('.empty-msg');
        if (emptyMsg) emptyMsg.remove();
        zone.appendChild(draggedCard);
        draggedCard.dataset.stageId = targetStageId;
        draggedCard.classList.remove('dragging');

        // Update counters
        updateStageCount(sourceStageId);
        updateStageCount(targetStageId);

        // Check if source is now empty
        var sourceZone = document.querySelector('.drop-zone[data-stage-id="' + sourceStageId + '"]');
        if (sourceZone && sourceZone.querySelectorAll('.deal-card').length === 0) {
            sourceZone.innerHTML = '<p class="text-xs text-gray-400 dark:text-gray-500 text-center py-4 empty-msg">Sin negocios</p>';
        }

        // POST to server
        var fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('pipeline_stage_id', targetStageId);
        fetch('/deals/' + draggedDealId + '/move', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) {
            if (!r.ok) {
                // Revert on failure
                window.location.reload();
            }
        }).catch(function() {
            window.location.reload();
        });
    }

    function updateStageCount(stageId) {
        var zone = document.querySelector('.drop-zone[data-stage-id="' + stageId + '"]');
        var counter = document.querySelector('.stage-count[data-stage-id="' + stageId + '"]');
        if (zone && counter) {
            counter.textContent = zone.querySelectorAll('.deal-card').length;
        }
    }

    // ── Drawer ──
    function openDealDrawer(dealId, event) {
        // Don't open drawer if we were dragging
        if (event && event.defaultPrevented) return;
        if (event) event.preventDefault();

        var overlay = document.getElementById('deal-drawer-overlay');
        var panel = document.getElementById('deal-drawer-panel');
        var content = document.getElementById('deal-drawer-content');

        overlay.classList.remove('hidden');
        content.innerHTML = '<div class="flex items-center justify-center h-32"><div class="text-gray-400">Cargando...</div></div>';
        setTimeout(function() { panel.classList.remove('translate-x-full'); }, 10);

        fetch('/deals/' + dealId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            content.innerHTML = renderDealDrawer(data);
        })
        .catch(function() {
            content.innerHTML = '<div class="p-4 text-red-500">Error al cargar el negocio.</div>';
        });
    }

    function closeDealDrawer() {
        var panel = document.getElementById('deal-drawer-panel');
        panel.classList.add('translate-x-full');
        setTimeout(function() {
            document.getElementById('deal-drawer-overlay').classList.add('hidden');
        }, 300);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDealDrawer();
    });

    function esc(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function renderDealDrawer(data) {
        var d = data.deal;
        var html = '';

        // Header
        html += '<div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">';
        html += '<h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 truncate">' + esc(d.contact_name) + '</h3>';
        html += '<button onclick="closeDealDrawer()" class="flex items-center justify-center w-10 h-10 rounded-full bg-red-500 text-white hover:bg-red-600 transition shadow leading-none"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>';
        html += '</div>';

        html += '<div class="p-4 space-y-6">';

        // Contact Info
        html += '<div>';
        html += '<h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Contacto</h4>';
        html += '<dl class="text-sm space-y-1">';
        html += '<div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Nombre</dt><dd class="text-gray-900 dark:text-gray-100">' + esc(d.contact_name) + '</dd></div>';
        if (d.contact_email) {
            html += '<div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="text-gray-900 dark:text-gray-100">' + esc(d.contact_email) + '</dd></div>';
        }
        if (d.contact_phone) {
            html += '<div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Teléfono</dt><dd class="text-gray-900 dark:text-gray-100">' + esc(d.contact_phone) + '</dd></div>';
        }
        html += '</dl></div>';

        // Deal Info
        html += '<div>';
        html += '<h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Negocio</h4>';
        html += '<dl class="text-sm space-y-1">';
        html += '<div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Pipeline</dt><dd class="text-gray-900 dark:text-gray-100">' + esc(d.pipeline.name) + '</dd></div>';
        html += '<div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Etapa</dt><dd><span class="inline-block w-2 h-2 rounded-full mr-1" style="background:' + esc(d.stage.color) + '"></span><span class="text-gray-900 dark:text-gray-100">' + esc(d.stage.name) + '</span></dd></div>';
        html += '<div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Valor</dt><dd class="text-gray-900 dark:text-gray-100">$' + Number(d.value).toLocaleString('en', {minimumFractionDigits:2}) + ' ' + esc(d.currency) + '</dd></div>';
        html += '<div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Estado</dt><dd class="text-gray-900 dark:text-gray-100 capitalize">' + esc(d.status) + '</dd></div>';
        if (d.expected_close_date) {
            html += '<div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Cierre Esperado</dt><dd class="text-gray-900 dark:text-gray-100">' + esc(d.expected_close_date) + '</dd></div>';
        }
        html += '<div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Asignado a</dt><dd class="text-gray-900 dark:text-gray-100">' + (d.assigned_user ? esc(d.assigned_user.name) : 'Sin asignar') + '</dd></div>';
        html += '</dl></div>';

        // Tags
        html += '<div>';
        html += '<h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Etiquetas</h4>';
        if (d.tags.length > 0) {
            html += '<div class="flex flex-wrap gap-1 mb-2">';
            d.tags.forEach(function(tag) {
                html += '<span class="text-[10px] font-semibold px-2 py-0.5 rounded-full text-white shadow-sm" style="background:' + esc(tag.color || '#6B7280') + '">' + esc(tag.name) + '</span>';
            });
            html += '</div>';
        }
        if (data.can_update && data.all_tags.length > 0) {
            html += '<form onsubmit="submitTagSync(event, ' + d.id + ')" class="mt-1">';
            html += '<div class="flex flex-wrap gap-2 mb-2">';
            var tagIds = d.tags.map(function(t) { return t.id; });
            data.all_tags.forEach(function(tag) {
                var checked = tagIds.indexOf(tag.id) !== -1 ? ' checked' : '';
                html += '<label class="inline-flex items-center gap-1 text-xs cursor-pointer">';
                html += '<input type="checkbox" name="tag_ids[]" value="' + tag.id + '"' + checked + ' class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">';
                html += '<span class="inline-block w-2 h-2 rounded-full" style="background:' + esc(tag.color) + '"></span>';
                html += '<span class="text-gray-600 dark:text-gray-400">' + esc(tag.name) + '</span>';
                html += '</label>';
            });
            html += '</div>';
            html += '<button type="submit" class="px-2 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">Actualizar Etiquetas</button>';
            html += '</form>';
        }
        html += '</div>';

        // Move Stage
        if (data.can_update) {
            html += '<div>';
            html += '<h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Mover Etapa</h4>';
            html += '<form onsubmit="submitMoveStage(event, ' + d.id + ')" class="flex gap-2">';
            html += '<select name="pipeline_stage_id" class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">';
            data.stages.forEach(function(s) {
                var sel = s.id === d.pipeline_stage_id ? ' selected' : '';
                html += '<option value="' + s.id + '"' + sel + '>' + esc(s.name) + '</option>';
            });
            html += '</select>';
            html += '<button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Mover</button>';
            html += '</form></div>';
        }

        // Assign
        if (data.can_assign) {
            html += '<div>';
            html += '<h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Reasignar</h4>';
            html += '<form onsubmit="submitAssign(event, ' + d.id + ')" class="flex gap-2">';
            html += '<select name="assigned_user_id" class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">';
            data.agents.forEach(function(a) {
                var sel = d.assigned_user_id === a.id ? ' selected' : '';
                html += '<option value="' + a.id + '"' + sel + '>' + esc(a.name) + '</option>';
            });
            html += '</select>';
            html += '<button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Asignar</button>';
            html += '</form></div>';
        }

        // Notes
        html += '<div>';
        html += '<h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Notas</h4>';
        html += '<form onsubmit="submitNote(event, ' + d.id + ')" class="mb-3">';
        html += '<textarea name="body" rows="2" required placeholder="Agregar nota..." class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"></textarea>';
        html += '<button type="submit" class="mt-1 px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">Agregar Nota</button>';
        html += '</form>';
        html += '<div class="space-y-2">';
        if (d.notes.length > 0) {
            d.notes.forEach(function(n) {
                html += '<div class="bg-gray-50 dark:bg-gray-700 rounded p-2">';
                html += '<div class="flex justify-between text-xs text-gray-400 mb-1"><span>' + esc(n.user) + '</span><span>' + esc(n.created_at) + '</span></div>';
                html += '<p class="text-sm text-gray-800 dark:text-gray-200">' + esc(n.body) + '</p>';
                html += '</div>';
            });
        } else {
            html += '<p class="text-xs text-gray-400">Sin notas aún.</p>';
        }
        html += '</div></div>';

        // Commissions
        html += '<div>';
        html += '<h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Comisiones</h4>';
        if (data.can_manage_commissions) {
            html += '<form onsubmit="submitCommission(event, ' + d.id + ')" class="mb-3 bg-gray-50 dark:bg-gray-700/50 rounded p-2">';
            html += '<div class="grid grid-cols-2 gap-2 mb-2">';
            html += '<select name="user_id" required class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"><option value="">Agente...</option>';
            data.agents.forEach(function(a) {
                html += '<option value="' + a.id + '">' + esc(a.name) + '</option>';
            });
            html += '</select>';
            html += '<div class="flex items-center gap-1"><input type="number" name="percentage" step="0.01" min="0" max="100" required placeholder="%" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"><span class="text-xs text-gray-500">%</span></div>';
            html += '</div>';
            html += '<div class="flex items-center justify-between"><span class="text-xs text-gray-400">Valor: $' + Number(d.value).toLocaleString('en', {minimumFractionDigits:2}) + '</span>';
            html += '<button type="submit" class="px-2 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700">Agregar</button></div>';
            html += '</form>';
        }
        html += '<div class="space-y-2">';
        var statusLabels = {pending:'Pendiente', approved:'Aprobada', paid:'Pagada', cancelled:'Cancelada'};
        var statusColors = {pending:'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300', approved:'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300', paid:'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300', cancelled:'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'};
        if (d.commissions.length > 0) {
            d.commissions.forEach(function(c) {
                html += '<div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 rounded p-2">';
                html += '<div><p class="text-sm text-gray-900 dark:text-gray-100">' + esc(c.user) + '</p>';
                html += '<p class="text-xs text-gray-400">' + c.percentage + '% · $' + Number(c.amount).toLocaleString('en', {minimumFractionDigits:2}) + '</p></div>';
                html += '<span class="px-2 py-0.5 text-xs rounded-full ' + (statusColors[c.status]||'') + '">' + (statusLabels[c.status]||c.status) + '</span>';
                html += '</div>';
            });
        } else {
            html += '<p class="text-xs text-gray-400">Sin comisiones.</p>';
        }
        html += '</div></div>';

        // Stage History
        html += '<div>';
        html += '<h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Historial de Etapas</h4>';
        html += '<div class="space-y-2">';
        if (d.stage_history.length > 0) {
            d.stage_history.forEach(function(h) {
                html += '<div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">';
                html += '<span>' + esc(h.from) + '</span><span>&rarr;</span>';
                html += '<span class="font-medium text-gray-700 dark:text-gray-300">' + esc(h.to) + '</span>';
                if (h.by) html += '<span class="ml-1">by ' + esc(h.by) + '</span>';
                html += '<span class="ml-auto">' + esc(h.at) + '</span>';
                html += '</div>';
            });
        } else {
            html += '<p class="text-xs text-gray-400">Sin historial.</p>';
        }
        html += '</div></div>';

        // Linked Conversation
        if (d.conversation) {
            html += '<div>';
            html += '<h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Conversación Vinculada</h4>';
            html += '<a href="' + esc(d.conversation.url) + '" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">' + esc(d.conversation.label) + '</a>';
            html += '</div>';
        }

        // Delete Deal
        if (data.can_delete) {
            html += '<div class="pt-4 border-t border-gray-200 dark:border-gray-700">';
            html += '<button onclick="deleteDeal(' + d.id + ')" class="w-full px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition">';
            html += 'Eliminar Negocio';
            html += '</button>';
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    // ── Form submissions via AJAX ──
    function postForm(url, formData) {
        formData.append('_token', csrfToken);
        return fetch(url, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    }

    function deleteDeal(dealId) {
        if (!confirm('¿Estás seguro de que deseas eliminar este negocio? Esta acción no se puede deshacer.')) return;

        fetch('/deals/' + dealId, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(function(r) {
            if (!r.ok) throw new Error('Error');
            return r.json();
        })
        .then(function() {
            closeDealDrawer();
            var card = document.querySelector('.deal-card[data-deal-id="' + dealId + '"]');
            if (card) {
                var zone = card.closest('.drop-zone');
                card.remove();
                // Update stage counter
                if (zone) {
                    var counter = zone.closest('.flex.flex-col').querySelector('.stage-count');
                    if (counter) counter.textContent = zone.querySelectorAll('.deal-card').length;
                    // Show empty message if no cards left
                    if (zone.querySelectorAll('.deal-card').length === 0) {
                        zone.innerHTML = '<p class="text-xs text-gray-400 italic text-center py-4 empty-msg">Sin negocios</p>';
                    }
                }
            }
        })
        .catch(function() {
            alert('Error al eliminar el negocio.');
        });
    }

    function refreshDealCard(dealId) {
        fetch('/deals/' + dealId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var card = document.querySelector('.deal-card[data-deal-id="' + dealId + '"]');
            if (!card) return;
            var d = data.deal;

            // Rebuild card content
            var html = '<div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">' + esc(d.contact_name) + '</div>';
            if (d.value > 0) {
                html += '<div class="text-xs text-gray-500 dark:text-gray-400 mt-1">$' + Number(d.value).toLocaleString('en', {minimumFractionDigits:2}) + ' ' + esc(d.currency) + '</div>';
            }
            html += '<div class="text-xs text-gray-400 mt-1">' + (d.assigned_user ? esc(d.assigned_user.name) : '<span class="italic">Sin asignar</span>') + '</div>';
            if (d.tags.length > 0) {
                html += '<div class="flex flex-wrap gap-1 mt-2">';
                d.tags.forEach(function(tag) {
                    html += '<span class="text-[10px] font-semibold px-2 py-0.5 rounded-full text-white shadow-sm" style="background:' + esc(tag.color || '#6B7280') + '">' + esc(tag.name) + '</span>';
                });
                html += '</div>';
            }

            card.innerHTML = html;
        });
    }

    function moveDealCard(dealId, newStageId) {
        var card = document.querySelector('.deal-card[data-deal-id="' + dealId + '"]');
        if (!card) return;

        var oldStageId = card.dataset.stageId;
        var targetZone = document.querySelector('.drop-zone[data-stage-id="' + newStageId + '"]');
        if (!targetZone) return;

        // Remove empty message from target
        var emptyMsg = targetZone.querySelector('.empty-msg');
        if (emptyMsg) emptyMsg.remove();

        // Move card
        targetZone.appendChild(card);
        card.dataset.stageId = newStageId;

        // Update counters
        updateStageCount(oldStageId);
        updateStageCount(newStageId);

        // Check if source is empty
        var sourceZone = document.querySelector('.drop-zone[data-stage-id="' + oldStageId + '"]');
        if (sourceZone && sourceZone.querySelectorAll('.deal-card').length === 0) {
            sourceZone.innerHTML = '<p class="text-xs text-gray-400 dark:text-gray-500 text-center py-4 empty-msg">Sin negocios</p>';
        }
    }

    function submitMoveStage(event, dealId) {
        event.preventDefault();
        var form = event.target;
        var fd = new FormData(form);
        var newStageId = fd.get('pipeline_stage_id');
        postForm('/deals/' + dealId + '/move', fd).then(function(r) {
            if (r.ok) {
                moveDealCard(dealId, newStageId);
                openDealDrawer(dealId);
            }
        });
    }

    function submitAssign(event, dealId) {
        event.preventDefault();
        var fd = new FormData(event.target);
        postForm('/deals/' + dealId + '/assign', fd).then(function() {
            refreshDealCard(dealId);
            openDealDrawer(dealId);
        });
    }

    function submitNote(event, dealId) {
        event.preventDefault();
        var fd = new FormData(event.target);
        postForm('/deals/' + dealId + '/notes', fd).then(function() {
            refreshDealCard(dealId);
            openDealDrawer(dealId);
        });
    }

    function submitTagSync(event, dealId) {
        event.preventDefault();
        var fd = new FormData(event.target);
        postForm('/deals/' + dealId + '/tags', fd).then(function() {
            refreshDealCard(dealId);
            openDealDrawer(dealId);
        });
    }

    function submitCommission(event, dealId) {
        event.preventDefault();
        var fd = new FormData(event.target);
        postForm('/deals/' + dealId + '/commissions', fd).then(function() {
            refreshDealCard(dealId);
            openDealDrawer(dealId);
        });
    }
    </script>
    @endpush
</x-app-layout>
