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
        @elseif($msg->type === 'template')
            <div class="flex justify-end items-start gap-1" data-msg-id="{{ $msg->id }}">
                <div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-crea-primary text-white text-sm shadow-sm border border-green-400/30">
                    <div class="text-[10px] italic text-white/60 mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Plantilla
                    </div>
                    {{ $msg->body }}
                    @if(($msg->metadata ?? [])['template_category'] ?? false)
                        <div class="text-[10px] text-white/40 mt-1">{{ ($msg->metadata ?? [])['template_name'] ?? '' }}</div>
                    @endif
                    <div class="text-[10px] text-crea-secondary-light mt-1 text-right">
                        {{ $msg->user?->name ?? 'Agent' }} &middot; {{ $msg->created_at->format('H:i') }}
                    </div>
                </div>
                <label class="msg-checkbox hidden self-center shrink-0 cursor-pointer">
                    <input type="checkbox" class="forward-check w-4 h-4 rounded border-gray-300 text-green-500 focus:ring-green-500" value="{{ $msg->id }}">
                </label>
            </div>
        @elseif($msg->isInbound())
            <div class="flex justify-start items-start gap-1" data-msg-id="{{ $msg->id }}">
                <label class="msg-checkbox hidden self-center shrink-0 cursor-pointer">
                    <input type="checkbox" class="forward-check w-4 h-4 rounded border-gray-300 text-green-500 focus:ring-green-500" value="{{ $msg->id }}">
                </label>
                <div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-bl-sm bg-white shadow-sm text-sm text-gray-800">
                    @if(($msg->metadata ?? [])['forwarded'] ?? false)
                        <div class="text-[10px] italic text-gray-400 mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            Reenviado
                        </div>
                    @endif
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
            <div class="flex justify-end items-start gap-1" data-msg-id="{{ $msg->id }}">
                <div class="max-w-[85%] sm:max-w-md px-4 py-2 rounded-2xl rounded-br-sm bg-crea-primary text-white text-sm shadow-sm">
                    @if(($msg->metadata ?? [])['forwarded'] ?? false)
                        <div class="text-[10px] italic text-white/60 mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            Reenviado
                        </div>
                    @endif
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
                <label class="msg-checkbox hidden self-center shrink-0 cursor-pointer">
                    <input type="checkbox" class="forward-check w-4 h-4 rounded border-gray-300 text-green-500 focus:ring-green-500" value="{{ $msg->id }}">
                </label>
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
