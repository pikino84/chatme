<x-app-layout>
    <x-slot name="header">{{ $template ? 'Editar Plantilla' : 'Crear Plantilla' }}</x-slot>

    @if(session('error'))
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Form --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <form method="POST"
                  action="{{ $template ? route('whatsapp-templates.update', $template) : route('whatsapp-templates.store') }}"
                  id="template-form">
                @csrf
                @if($template)
                    @method('PUT')
                @endif

                {{-- Channel --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Canal WhatsApp</label>
                    <select name="channel_id" id="tpl-channel" required class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        @foreach($channels as $ch)
                            <option value="{{ $ch->id }}" @selected(old('channel_id', $template?->channel_id) == $ch->id)>{{ $ch->name }}</option>
                        @endforeach
                    </select>
                    @error('channel_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Name --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre (solo minúsculas y guiones bajos)</label>
                    <input type="text" name="name" id="tpl-name" value="{{ old('name', $template?->name) }}" required
                           pattern="^[a-z][a-z0-9_]*$" maxlength="255"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                           placeholder="ej: saludo_bienvenida" oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'');updatePreview()">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Language --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Idioma</label>
                    <select name="language" id="tpl-language" required class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" onchange="updatePreview()">
                        @foreach(['es_MX' => 'Español (México)', 'es' => 'Español', 'en_US' => 'English (US)', 'en' => 'English', 'pt_BR' => 'Português (BR)'] as $code => $label)
                            <option value="{{ $code }}" @selected(old('language', $template?->language ?? 'es_MX') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Category --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoría</label>
                    <div class="flex gap-4">
                        @foreach(['MARKETING' => 'Marketing', 'UTILITY' => 'Utilidad', 'AUTHENTICATION' => 'Autenticación'] as $val => $label)
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="radio" name="category" value="{{ $val }}" @checked(old('category', $template?->category ?? 'UTILITY') === $val) class="text-green-500 focus:ring-green-500" onchange="updatePreview()">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('category') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Header --}}
                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Encabezado (opcional)</label>
                    <select name="header_type" id="tpl-header-type" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 mb-2" onchange="toggleHeaderFields();updatePreview()">
                        <option value="none" @selected(old('header_type', $template?->header_type) === 'none' || !old('header_type', $template?->header_type))>Ninguno</option>
                        <option value="text" @selected(old('header_type', $template?->header_type) === 'text')>Texto</option>
                        <option value="image" @selected(old('header_type', $template?->header_type) === 'image')>Imagen</option>
                        <option value="video" @selected(old('header_type', $template?->header_type) === 'video')>Video</option>
                        <option value="document" @selected(old('header_type', $template?->header_type) === 'document')>Documento</option>
                    </select>
                    <div id="header-text-field" class="hidden">
                        <input type="text" name="header_text" id="tpl-header-text" value="{{ old('header_text') }}" maxlength="60"
                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                               placeholder="Texto del encabezado" oninput="updatePreview()">
                    </div>
                    <div id="header-media-field" class="hidden">
                        <input type="file" id="header-file-input" accept="image/*,video/*,.pdf,.doc,.docx" class="w-full text-sm">
                        <input type="hidden" name="header_handle" id="tpl-header-handle">
                        <input type="hidden" name="header_media_path" id="tpl-header-media-path">
                        <div id="header-upload-status" class="text-xs text-gray-500 mt-1"></div>
                    </div>
                </div>

                {{-- Body --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cuerpo del mensaje</label>
                    <div class="flex gap-1 mb-1">
                        <button type="button" onclick="insertVariable()" class="px-2 py-1 text-xs bg-gray-200 dark:bg-gray-600 rounded hover:bg-gray-300 transition">+ Variable @{{ N }}</button>
                    </div>
                    <textarea name="body_text" id="tpl-body" rows="4" required maxlength="1024"
                              class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                              placeholder="Hola {{1}}, tu pedido {{2}} está listo."
                              oninput="updatePreview();updateExampleFields()">{{ old('body_text', $template?->body_text) }}</textarea>
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span id="body-var-count">0 variables</span>
                        <span><span id="body-char-count">0</span>/1024</span>
                    </div>
                    @error('body_text') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Example values --}}
                <div id="example-fields" class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valores de ejemplo (para aprobación de Meta)</label>
                    <div id="example-inputs" class="space-y-2"></div>
                </div>

                {{-- Footer --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pie de página (opcional)</label>
                    <input type="text" name="footer_text" id="tpl-footer" value="{{ old('footer_text', $template?->footer_text) }}" maxlength="60"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                           placeholder="Ej: Responde STOP para cancelar" oninput="updatePreview()">
                </div>

                {{-- Buttons --}}
                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Botones (opcional, máx. 3)</label>
                        <button type="button" onclick="addButton()" id="add-btn" class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200 transition">+ Agregar</button>
                    </div>
                    <div id="buttons-container" class="space-y-2"></div>
                </div>

                <button type="submit" class="w-full px-4 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                    {{ $template ? 'Actualizar y Reenviar a Meta' : 'Crear y Enviar a Meta' }}
                </button>
            </form>
        </div>

        {{-- Live Preview --}}
        <div class="lg:sticky lg:top-6 self-start">
            <div class="bg-gray-100 dark:bg-gray-700 rounded-xl p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3 text-center">Vista previa</p>
                <div class="mx-auto w-72 bg-[#e5ddd5] rounded-2xl overflow-hidden shadow-lg">
                    {{-- Phone header --}}
                    <div class="bg-[#075e54] text-white px-4 py-3 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-xs">WA</div>
                        <span class="text-sm font-medium">Cliente</span>
                    </div>
                    {{-- Messages area --}}
                    <div class="p-3 min-h-[200px]">
                        <div class="flex justify-end">
                            <div class="bg-[#dcf8c6] rounded-lg rounded-tr-sm p-2.5 max-w-[85%] shadow-sm">
                                <div id="preview-header" class="hidden text-xs font-bold text-gray-800 mb-1"></div>
                                <div id="preview-body" class="text-sm text-gray-800 whitespace-pre-wrap"></div>
                                <div id="preview-footer" class="hidden text-[10px] text-gray-500 mt-1"></div>
                                <div class="text-[10px] text-gray-400 mt-1 text-right">ahora</div>
                            </div>
                        </div>
                        <div id="preview-buttons" class="mt-1 flex justify-end"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    var csrfToken = '{{ csrf_token() }}';
    var existingButtons = @json(old('buttons', $template?->buttons ?? []));
    var existingExamples = @json(old('body_example_values', $template?->body_example_values ?? []));

    document.addEventListener('DOMContentLoaded', function() {
        toggleHeaderFields();
        updatePreview();
        updateExampleFields();

        // Restore existing buttons
        if (existingButtons && existingButtons.length) {
            existingButtons.forEach(function(btn) { addButton(btn); });
        }

        // Header file upload
        var fileInput = document.getElementById('header-file-input');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                var file = this.files[0];
                if (!file) return;
                var channelId = document.getElementById('tpl-channel').value;
                if (!channelId) { alert('Selecciona un canal primero'); return; }

                var statusEl = document.getElementById('header-upload-status');
                statusEl.textContent = 'Subiendo...';

                var fd = new FormData();
                fd.append('file', file);
                fd.append('channel_id', channelId);
                fd.append('_token', csrfToken);

                fetch('{{ route("whatsapp-templates.upload-media") }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    document.getElementById('tpl-header-handle').value = data.handle || '';
                    document.getElementById('tpl-header-media-path').value = data.s3_path || '';
                    statusEl.textContent = data.handle ? 'Subido correctamente' : 'Subido a S3 (handle pendiente)';
                    statusEl.className = 'text-xs mt-1 ' + (data.handle ? 'text-green-600' : 'text-yellow-600');
                })
                .catch(function() {
                    statusEl.textContent = 'Error al subir';
                    statusEl.className = 'text-xs mt-1 text-red-500';
                });
            });
        }
    });

    function toggleHeaderFields() {
        var type = document.getElementById('tpl-header-type').value;
        document.getElementById('header-text-field').classList.toggle('hidden', type !== 'text');
        document.getElementById('header-media-field').classList.toggle('hidden', !['image','video','document'].includes(type));
    }

    function insertVariable() {
        var textarea = document.getElementById('tpl-body');
        var text = textarea.value;
        // Find next variable number (max existing + 1)
        var nums = (text.match(/\{\{(\d+)\}\}/g) || []).map(function(m) { return parseInt(m.replace(/[{}]/g, ''), 10); });
        var next = nums.length ? Math.max.apply(null, nums) + 1 : 1;
        var pos = textarea.selectionStart;
        textarea.value = text.slice(0, pos) + '{{' + next + '}}' + text.slice(pos);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = pos + String('{{' + next + '}}').length;
        updatePreview();
        updateExampleFields();
    }

    var buttonCount = 0;
    function addButton(existing) {
        if (buttonCount >= 3) return;
        var container = document.getElementById('buttons-container');
        var idx = buttonCount;
        buttonCount++;
        if (buttonCount >= 3) document.getElementById('add-btn').classList.add('hidden');

        var div = document.createElement('div');
        div.className = 'flex gap-2 items-start p-2 bg-white dark:bg-gray-600 rounded';
        div.innerHTML = '<select name="buttons[' + idx + '][type]" class="text-xs rounded border-gray-300 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-200" onchange="toggleBtnFields(this,' + idx + ');updatePreview()">' +
            '<option value="QUICK_REPLY"' + (existing && existing.type === 'QUICK_REPLY' ? ' selected' : '') + '>Respuesta rápida</option>' +
            '<option value="URL"' + (existing && existing.type === 'URL' ? ' selected' : '') + '>URL</option>' +
            '<option value="PHONE_NUMBER"' + (existing && existing.type === 'PHONE_NUMBER' ? ' selected' : '') + '>Teléfono</option>' +
            '</select>' +
            '<input type="text" name="buttons[' + idx + '][text]" placeholder="Texto del botón" maxlength="25" value="' + escHtml(existing ? existing.text || '' : '') + '" class="flex-1 text-xs rounded border-gray-300 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-200" oninput="updatePreview()">' +
            '<input type="text" name="buttons[' + idx + '][url]" placeholder="URL" value="' + escHtml(existing ? existing.url || '' : '') + '" class="flex-1 text-xs rounded border-gray-300 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-200 btn-url-' + idx + ' hidden">' +
            '<input type="text" name="buttons[' + idx + '][phone_number]" placeholder="Teléfono" value="' + escHtml(existing ? existing.phone_number || '' : '') + '" class="flex-1 text-xs rounded border-gray-300 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-200 btn-phone-' + idx + ' hidden">' +
            '<button type="button" onclick="removeButton(this)" class="text-red-500 hover:text-red-700 text-xs shrink-0">X</button>';
        container.appendChild(div);

        if (existing) {
            var select = div.querySelector('select');
            toggleBtnFields(select, idx);
        }
        updatePreview();
    }

    function toggleBtnFields(select, idx) {
        var val = select.value;
        var urlEl = document.querySelector('.btn-url-' + idx);
        var phoneEl = document.querySelector('.btn-phone-' + idx);
        if (urlEl) urlEl.classList.toggle('hidden', val !== 'URL');
        if (phoneEl) phoneEl.classList.toggle('hidden', val !== 'PHONE_NUMBER');
    }

    function removeButton(btn) {
        btn.closest('div').remove();
        buttonCount--;
        document.getElementById('add-btn').classList.remove('hidden');
        updatePreview();
    }

    function updateExampleFields() {
        var body = document.getElementById('tpl-body').value;
        var matches = body.match(/\{\{(\d+)\}\}/g) || [];
        var unique = [...new Set(matches)].sort();
        var container = document.getElementById('example-inputs');
        var wrapper = document.getElementById('example-fields');

        if (!unique.length) {
            wrapper.classList.add('hidden');
            container.innerHTML = '';
            document.getElementById('body-var-count').textContent = '0 variables';
            return;
        }

        wrapper.classList.remove('hidden');
        document.getElementById('body-var-count').textContent = unique.length + ' variable' + (unique.length > 1 ? 's' : '');

        container.innerHTML = '';
        unique.forEach(function(v, i) {
            var existing = existingExamples && existingExamples[i] ? existingExamples[i] : '';
            var div = document.createElement('div');
            div.className = 'flex items-center gap-2';
            div.innerHTML = '<span class="text-xs text-gray-500 w-10 shrink-0">' + v + '</span>' +
                '<input type="text" name="body_example_values[]" value="' + escHtml(existing) + '" placeholder="Valor de ejemplo" class="flex-1 text-xs rounded border-gray-300 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-200" oninput="updatePreview()">';
            container.appendChild(div);
        });
    }

    function updatePreview() {
        // Body
        var body = document.getElementById('tpl-body').value;
        document.getElementById('body-char-count').textContent = body.length;

        // Replace variables with example values
        var exampleInputs = document.querySelectorAll('#example-inputs input');
        exampleInputs.forEach(function(inp, i) {
            var val = inp.value || '[' + (i + 1) + ']';
            body = body.replace('{{' + (i + 1) + '}}', val);
        });
        document.getElementById('preview-body').textContent = body;

        // Header
        var headerType = document.getElementById('tpl-header-type').value;
        var previewHeader = document.getElementById('preview-header');
        if (headerType === 'text') {
            var headerText = document.getElementById('tpl-header-text').value || '';
            previewHeader.textContent = headerText;
            previewHeader.classList.toggle('hidden', !headerText);
        } else if (['image','video','document'].includes(headerType)) {
            previewHeader.textContent = '[' + headerType.charAt(0).toUpperCase() + headerType.slice(1) + ']';
            previewHeader.classList.remove('hidden');
        } else {
            previewHeader.classList.add('hidden');
        }

        // Footer
        var footer = document.getElementById('tpl-footer').value;
        var previewFooter = document.getElementById('preview-footer');
        previewFooter.textContent = footer;
        previewFooter.classList.toggle('hidden', !footer);

        // Buttons
        var previewBtns = document.getElementById('preview-buttons');
        var btnEls = document.querySelectorAll('#buttons-container > div');
        if (!btnEls.length) {
            previewBtns.innerHTML = '';
            return;
        }
        var html = '<div class="w-[85%] space-y-1">';
        btnEls.forEach(function(el) {
            var text = el.querySelector('input[name*="[text]"]').value || 'Botón';
            html += '<div class="bg-white rounded py-1.5 text-center text-xs text-[#00a884] font-medium shadow-sm">' + escHtml(text) + '</div>';
        });
        html += '</div>';
        previewBtns.innerHTML = html;
    }

    function escHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML.replace(/"/g, '&quot;');
    }
    </script>
    @endpush
</x-app-layout>
