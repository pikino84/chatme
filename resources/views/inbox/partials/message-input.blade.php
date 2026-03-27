<div class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 sm:p-3">
    <form id="message-form" method="POST" action="{{ route('inbox.conversations.messages.store', $conversation) }}" class="flex gap-2 items-end">
        @csrf
        <input type="hidden" name="type" id="message-type" value="text">

        <div class="flex-1">
            <textarea name="body" id="message-body" rows="2" required
                      placeholder="Escribe un mensaje..."
                      class="w-full text-base sm:text-sm rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500 resize-none py-3 px-4"></textarea>
        </div>

        <div class="flex flex-col gap-1.5 shrink-0">
            <button type="submit" data-type="text"
                    class="px-4 py-3 sm:px-3 sm:py-2 text-sm sm:text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl active:scale-95 transition-transform">
                Enviar
            </button>
            @can('sendInternalNote', [App\Models\Message::class, $conversation])
            <button type="submit" data-type="internal_note"
                    class="px-4 py-3 sm:px-3 sm:py-2 text-sm sm:text-xs font-medium text-yellow-700 bg-yellow-100 hover:bg-yellow-200 dark:text-yellow-300 dark:bg-yellow-900/30 dark:hover:bg-yellow-900/50 rounded-xl active:scale-95 transition-transform">
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
    var thread = document.getElementById('message-thread');
    var sending = false;

    if (!form) return;

    form.querySelectorAll('button[data-type]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            typeInput.value = this.getAttribute('data-type');
        });
    });

    function sendMessage(e) {
        e.preventDefault();
        if (sending) return;

        var body = bodyInput.value.trim();
        if (!body) return;

        var type = typeInput.value;
        sending = true;

        var msgDiv = document.createElement('div');
        msgDiv.setAttribute('data-optimistic', 'true');
        if (type === 'internal_note') {
            msgDiv.className = 'flex justify-center';
            msgDiv.innerHTML = '<div class="max-w-md px-3 py-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-xs text-yellow-700 dark:text-yellow-300">' +
                '<span class="font-medium">Yo:</span> ' + escapeHtml(body) +
                ' <span class="text-yellow-400 ml-2">' + nowTime() + '</span></div>';
        } else {
            msgDiv.className = 'flex justify-end';
            msgDiv.innerHTML = '<div class="max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-indigo-600 text-white text-sm shadow-sm">' +
                escapeHtml(body) +
                '<div class="text-[10px] text-indigo-200 mt-1 text-right">Yo &middot; ' + nowTime() + '</div></div>';
        }
        thread.appendChild(msgDiv);
        thread.scrollTop = thread.scrollHeight;

        bodyInput.value = '';
        typeInput.value = 'text';

        var formData = new FormData(form);
        formData.set('body', body);
        formData.set('type', type);

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
        .catch(function() {
            sending = false;
            window.location.reload();
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
});
</script>
