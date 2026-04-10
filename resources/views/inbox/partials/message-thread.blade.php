<div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-3" id="message-thread">
    @foreach($messages as $msg)
        @if($msg->isInternalNote())
            <div class="flex justify-center" data-msg-id="{{ $msg->id }}">
                <div class="max-w-[85%] sm:max-w-md px-3 py-2 rounded-lg bg-yellow-50 border border-yellow-200 text-xs text-yellow-700">
                    <span class="font-medium">{{ $msg->user?->name ?? 'System' }}:</span>
                    {{ $msg->body }}
                    <span class="text-yellow-400 ml-2">{{ $msg->created_at->format('H:i') }}</span>
                </div>
            </div>
        @elseif($msg->isInbound())
            <div class="flex justify-start" data-msg-id="{{ $msg->id }}">
                <div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-bl-sm bg-white shadow-sm text-sm text-gray-800">
                    @include('inbox.partials.message-attachments', ['attachments' => $msg->attachments])
                    @if($msg->body && !$msg->isMediaMessage())
                        {{ $msg->body }}
                    @elseif($msg->body && $msg->isMediaMessage() && !in_array($msg->body, ['[Image]', '[Audio]', '[Video]', '[Document]', '[Sticker]']))
                        <p class="mt-1 text-xs text-gray-500">{{ $msg->body }}</p>
                    @endif
                    <div class="text-[10px] text-gray-400 mt-1 text-right">
                        {{ $msg->created_at->format('H:i') }}
                    </div>
                </div>
            </div>
        @else
            <div class="flex justify-end" data-msg-id="{{ $msg->id }}">
                <div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-crea-primary text-white text-sm shadow-sm">
                    @include('inbox.partials.message-attachments', ['attachments' => $msg->attachments])
                    @if($msg->body && !$msg->isMediaMessage())
                        {{ $msg->body }}
                    @elseif($msg->body && $msg->isMediaMessage() && !in_array($msg->body, ['[Image]', '[Audio]', '[Video]', '[Document]', '[Sticker]']))
                        <p class="mt-1 text-xs text-white/70">{{ $msg->body }}</p>
                    @endif
                    <div class="text-[10px] text-crea-secondary-light mt-1 text-right">
                        {{ $msg->user?->name ?? 'Agent' }} &middot; {{ $msg->created_at->format('H:i') }}
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var el = document.getElementById('message-thread');
        if (el) el.scrollTop = el.scrollHeight;
    });
</script>
