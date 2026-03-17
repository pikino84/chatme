<x-app-layout>
    <div class="max-w-3xl mx-auto py-8 px-4">
        <div class="mb-6">
            <a href="{{ route('pipelines.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">&larr; Pipelines</a>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mt-2">
                {{ $pipeline ? 'Editar Pipeline' : 'Nuevo Pipeline' }}
            </h2>
        </div>

        @if($errors->any())
            <div class="mb-4 px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ $pipeline ? route('pipelines.update', $pipeline) : route('pipelines.store') }}" method="POST"
              class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 space-y-5"
              id="pipeline-form">
            @csrf
            @if($pipeline)
                @method('PUT')
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del Pipeline *</label>
                <input type="text" name="name" value="{{ old('name', $pipeline?->name) }}" required
                       placeholder="Ej: Ventas Principal"
                       class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Etapas *</label>
                    <button type="button" id="btn-add-stage"
                            class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        + Agregar Etapa
                    </button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    Arrastra para reordenar. Marca una etapa como "Ganado" o "Perdido" para indicar cierre del negocio.
                </p>

                <div id="stages-container" class="space-y-2">
                    {{-- Stages rendered by JS --}}
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('pipelines.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancelar</a>
                <button type="submit"
                        class="px-5 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    {{ $pipeline ? 'Guardar Cambios' : 'Crear Pipeline' }}
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    (function() {
        var container = document.getElementById('stages-container');
        var btnAdd = document.getElementById('btn-add-stage');
        var stageIndex = 0;

        var initialStages = @json($stagesJson);

        function createStageRow(data) {
            var idx = stageIndex++;
            var row = document.createElement('div');
            row.className = 'stage-row flex items-start gap-2 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600';
            row.setAttribute('draggable', 'true');
            row.dataset.index = idx;

            var hasDels = data.has_deals ? 'true' : 'false';

            row.innerHTML =
                '<div class="cursor-grab text-gray-400 dark:text-gray-500 pt-2" title="Arrastrar para reordenar">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>' +
                '</div>' +
                '<input type="hidden" name="stages[' + idx + '][id]" value="' + (data.id || '') + '">' +
                '<div class="flex-1 grid grid-cols-1 sm:grid-cols-12 gap-2">' +
                    '<div class="sm:col-span-4">' +
                        '<input type="text" name="stages[' + idx + '][name]" value="' + escapeHtml(data.name || '') + '" required placeholder="Nombre de etapa" ' +
                               'class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500">' +
                    '</div>' +
                    '<div class="sm:col-span-1">' +
                        '<input type="color" name="stages[' + idx + '][color]" value="' + (data.color || '#6B7280') + '" ' +
                               'class="h-9 w-full rounded border-gray-300 dark:border-gray-600 cursor-pointer" title="Color">' +
                    '</div>' +
                    '<div class="sm:col-span-2">' +
                        '<input type="number" name="stages[' + idx + '][max_duration_hours]" value="' + (data.max_duration_hours || '') + '" min="1" placeholder="Max hrs" ' +
                               'class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500" title="Duración máxima (horas)">' +
                    '</div>' +
                    '<div class="sm:col-span-2 flex items-center gap-1">' +
                        '<label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">' +
                            '<input type="checkbox" name="stages[' + idx + '][is_won]" value="1" ' + (data.is_won ? 'checked' : '') +
                                   ' class="rounded border-gray-300 text-green-600 focus:ring-green-500" onchange="handleTerminal(this, \'won\')">' +
                            'Ganado' +
                        '</label>' +
                    '</div>' +
                    '<div class="sm:col-span-2 flex items-center gap-1">' +
                        '<label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">' +
                            '<input type="checkbox" name="stages[' + idx + '][is_lost]" value="1" ' + (data.is_lost ? 'checked' : '') +
                                   ' class="rounded border-gray-300 text-red-600 focus:ring-red-500" onchange="handleTerminal(this, \'lost\')">' +
                            'Perdido' +
                        '</label>' +
                    '</div>' +
                    '<div class="sm:col-span-1 flex items-center justify-end">' +
                        (hasDels === 'false'
                            ? '<button type="button" onclick="removeStage(this)" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" title="Eliminar etapa">' +
                                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                              '</button>'
                            : '<span class="text-xs text-gray-400" title="No se puede eliminar: tiene negocios">&#128274;</span>'
                        ) +
                    '</div>' +
                '</div>';

            container.appendChild(row);
            setupDragDrop(row);
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML.replace(/"/g, '&quot;');
        }

        window.removeStage = function(btn) {
            var rows = container.querySelectorAll('.stage-row');
            if (rows.length <= 1) {
                alert('El pipeline debe tener al menos una etapa.');
                return;
            }
            btn.closest('.stage-row').remove();
        };

        window.handleTerminal = function(checkbox, type) {
            var row = checkbox.closest('.stage-row');
            if (type === 'won' && checkbox.checked) {
                var lostCb = row.querySelector('input[name*="is_lost"]');
                if (lostCb) lostCb.checked = false;
            } else if (type === 'lost' && checkbox.checked) {
                var wonCb = row.querySelector('input[name*="is_won"]');
                if (wonCb) wonCb.checked = false;
            }
        };

        // Drag & drop
        var dragSrc = null;

        function setupDragDrop(row) {
            row.addEventListener('dragstart', function(e) {
                dragSrc = this;
                this.style.opacity = '0.5';
                e.dataTransfer.effectAllowed = 'move';
            });
            row.addEventListener('dragend', function() {
                this.style.opacity = '1';
                container.querySelectorAll('.stage-row').forEach(function(r) {
                    r.classList.remove('border-indigo-500');
                });
            });
            row.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('border-indigo-500');
            });
            row.addEventListener('dragleave', function() {
                this.classList.remove('border-indigo-500');
            });
            row.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('border-indigo-500');
                if (dragSrc !== this) {
                    var rows = Array.from(container.querySelectorAll('.stage-row'));
                    var srcIdx = rows.indexOf(dragSrc);
                    var tgtIdx = rows.indexOf(this);
                    if (srcIdx < tgtIdx) {
                        container.insertBefore(dragSrc, this.nextSibling);
                    } else {
                        container.insertBefore(dragSrc, this);
                    }
                }
            });
        }

        // Reindex before submit
        document.getElementById('pipeline-form').addEventListener('submit', function() {
            var rows = container.querySelectorAll('.stage-row');
            rows.forEach(function(row, i) {
                row.querySelectorAll('input, select').forEach(function(input) {
                    var name = input.getAttribute('name');
                    if (name) {
                        input.setAttribute('name', name.replace(/stages\[\d+\]/, 'stages[' + i + ']'));
                    }
                });
            });
        });

        btnAdd.addEventListener('click', function() {
            createStageRow({ id: null, name: '', color: '#6B7280', is_won: false, is_lost: false, max_duration_hours: null, has_deals: false });
        });

        // Init
        initialStages.forEach(function(s) { createStageRow(s); });
    })();
    </script>
    @endpush
</x-app-layout>
