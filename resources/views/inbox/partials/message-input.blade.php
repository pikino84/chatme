<div class="border-t border-gray-200 bg-white p-2 sm:p-3">
    {{-- File preview --}}
    <div id="file-preview" class="hidden mb-2 p-2 rounded-lg bg-gray-50 border border-gray-200">
        <div class="flex items-center gap-3">
            <div id="file-preview-thumb" class="w-12 h-12 rounded-lg bg-gray-200 flex items-center justify-center overflow-hidden shrink-0">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p id="file-preview-name" class="text-xs font-medium text-gray-700 truncate"></p>
                <p id="file-preview-size" class="text-[10px] text-gray-400"></p>
            </div>
            <button type="button" onclick="clearFile()" class="w-8 h-8 rounded-full bg-red-100 text-red-500 hover:bg-red-200 flex items-center justify-center shrink-0 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <form id="message-form" method="POST" action="{{ route('inbox.conversations.messages.store', $conversation) }}" enctype="multipart/form-data" class="flex gap-2 items-end">
        @csrf
        <input type="hidden" name="type" id="message-type" value="text">
        <input type="file" name="file" id="file-input" class="hidden" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip">

        {{-- Attach button --}}
        <button type="button" onclick="document.getElementById('file-input').click()"
                class="w-10 h-10 sm:w-8 sm:h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 flex items-center justify-center shrink-0 transition active:scale-95"
                title="Adjuntar archivo">
            <svg class="w-5 h-5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
        </button>

        <div class="flex-1">
            <textarea name="body" id="message-body" rows="2"
                      placeholder="Escribe un mensaje..."
                      class="w-full text-base sm:text-sm rounded-xl border-gray-300 focus:ring-crea-secondary focus:border-crea-secondary resize-none py-3 px-4"></textarea>
        </div>

        <div class="flex flex-col gap-1.5 shrink-0">
            <button type="submit" data-type="text"
                    class="px-4 py-3 sm:px-3 sm:py-2 text-sm sm:text-xs font-medium text-white bg-crea-primary hover:bg-crea-secondary rounded-xl active:scale-95 transition">
                Enviar
            </button>
            @can('sendInternalNote', [App\Models\Message::class, $conversation])
            <button type="submit" data-type="internal_note"
                    class="px-4 py-3 sm:px-3 sm:py-2 text-sm sm:text-xs font-medium text-yellow-700 bg-yellow-100 hover:bg-yellow-200 rounded-xl active:scale-95 transition">
                Nota
            </button>
            @endcan
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('message-form');
    var typeInput = document.getElementById('message-type');
    var bodyInput = document.getElementById('message-body');
    var fileInput = document.getElementById('file-input');
    var thread = document.getElementById('message-thread');
    var sending = false;

    if (!form) return;

    form.querySelectorAll('button[data-type]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            typeInput.value = this.getAttribute('data-type');
        });
    });

    // File selection handler
    fileInput.addEventListener('change', function() {
        var file = this.files[0];
        if (!file) { clearFile(); return; }

        var preview = document.getElementById('file-preview');
        var thumb = document.getElementById('file-preview-thumb');
        document.getElementById('file-preview-name').textContent = file.name;
        document.getElementById('file-preview-size').textContent = formatSize(file.size);
        preview.classList.remove('hidden');

        // Show image thumbnail
        if (file.type.startsWith('image/')) {
            var reader = new FileReader();
            reader.onload = function(e) {
                thumb.innerHTML = '<img src="' + e.target.result + '" class="w-12 h-12 object-cover rounded-lg">';
            };
            reader.readAsDataURL(file);
        } else if (file.type.startsWith('video/')) {
            thumb.innerHTML = '<svg class="w-6 h-6 text-crea-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        } else if (file.type.startsWith('audio/')) {
            thumb.innerHTML = '<svg class="w-6 h-6 text-crea-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>';
        } else {
            thumb.innerHTML = '<svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
        }

        // Make body optional when file is present
        bodyInput.removeAttribute('required');
    });

    function sendMessage(e) {
        e.preventDefault();
        if (sending) return;

        var body = bodyInput.value.trim();
        var hasFile = fileInput.files.length > 0;
        if (!body && !hasFile) return;

        var type = typeInput.value;
        sending = true;

        // Optimistic append
        var msgDiv = document.createElement('div');
        msgDiv.setAttribute('data-optimistic', 'true');
        if (type === 'internal_note') {
            msgDiv.className = 'flex justify-center';
            msgDiv.innerHTML = '<div class="max-w-md px-3 py-2 rounded-lg bg-yellow-50 border border-yellow-200 text-xs text-yellow-700">' +
                '<span class="font-medium">Yo:</span> ' + escapeHtml(body) +
                ' <span class="text-yellow-400 ml-2">' + nowTime() + '</span></div>';
        } else {
            msgDiv.className = 'flex justify-end';
            var content = '';
            if (hasFile) {
                var file = fileInput.files[0];
                if (file.type.startsWith('image/')) {
                    content = '<div class="mb-1 text-xs opacity-70">Enviando imagen...</div>';
                } else {
                    content = '<div class="mb-1 text-xs opacity-70">Enviando ' + escapeHtml(file.name) + '...</div>';
                }
            }
            if (body) content += escapeHtml(body);
            msgDiv.innerHTML = '<div class="max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-crea-primary text-white text-sm shadow-sm">' +
                content +
                '<div class="text-[10px] text-crea-secondary-light mt-1 text-right">Yo &middot; ' + nowTime() + '</div></div>';
        }
        thread.appendChild(msgDiv);
        thread.scrollTop = thread.scrollHeight;

        // Build FormData
        var formData = new FormData(form);
        if (body) formData.set('body', body);
        formData.set('type', type);

        bodyInput.value = '';
        typeInput.value = 'text';
        clearFile();

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value
            },
            body: formData
        })
        .then(function(r) {
            if (!r.ok) throw new Error('Send failed');
            return r.json();
        })
        .then(function(data) {
            if (data.message && data.message.id) {
                msgDiv.setAttribute('data-msg-id', data.message.id);
                msgDiv.removeAttribute('data-optimistic');
                if (window.__chatmeSentIds) {
                    window.__chatmeSentIds[data.message.id] = true;
                }
            }
            sending = false;
        })
        .catch(function(err) {
            sending = false;
            console.warn('[ChatMe] Send failed:', err);
            msgDiv.querySelector('div').style.opacity = '0.5';
        });
    }

    form.addEventListener('submit', sendMessage);

    bodyInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            typeInput.value = typeInput.value || 'text';
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function nowTime() {
        var d = new Date();
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function formatSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB';
        return bytes + ' B';
    }
});

function clearFile() {
    var fileInput = document.getElementById('file-input');
    fileInput.value = '';
    document.getElementById('file-preview').classList.add('hidden');
    var bodyInput = document.getElementById('message-body');
    if (bodyInput) bodyInput.setAttribute('required', '');
}
</script>
