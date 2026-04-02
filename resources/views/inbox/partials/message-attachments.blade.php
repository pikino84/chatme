@if($attachments->count())
    @foreach($attachments as $att)
        <div class="mb-1">
            @if($att->isPending())
                <div class="flex items-center gap-2 py-2 text-xs opacity-60">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Descargando {{ $att->media_type }}...
                </div>
            @elseif($att->isFailed())
                <div class="flex items-center gap-2 py-2 text-xs text-red-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Error al descargar archivo
                </div>
            @elseif($att->isImage())
                <a href="{{ $att->url() }}" target="_blank" class="block">
                    <img src="{{ $att->thumbnailUrl() ?? $att->url() }}" alt="{{ $att->file_name }}" class="max-w-[250px] max-h-[250px] rounded-lg object-cover cursor-pointer hover:opacity-90 transition" loading="lazy">
                </a>
            @elseif($att->isVideo())
                <video controls preload="metadata" class="max-w-[280px] max-h-[250px] rounded-lg">
                    <source src="{{ $att->url() }}" type="{{ $att->mime_type }}">
                </video>
            @elseif($att->isAudio())
                <div class="flex items-center gap-2 min-w-[200px]">
                    <audio controls preload="metadata" class="h-10 w-full max-w-[250px]">
                        <source src="{{ $att->url() }}" type="{{ $att->mime_type }}">
                    </audio>
                    @if($att->durationForHumans())
                        <span class="text-[10px] opacity-60">{{ $att->durationForHumans() }}</span>
                    @endif
                </div>
            @elseif($att->isDocument())
                <a href="{{ $att->url() }}" target="_blank" class="flex items-center gap-3 p-2 rounded-lg bg-black/5 hover:bg-black/10 transition min-w-[180px]">
                    <div class="w-10 h-10 rounded-lg bg-crea-secondary/10 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-crea-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium truncate">{{ $att->file_name }}</p>
                        <p class="text-[10px] opacity-60">{{ $att->sizeForHumans() }}</p>
                    </div>
                </a>
            @endif
        </div>
    @endforeach
@endif
