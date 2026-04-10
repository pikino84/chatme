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
                <img src="{{ $att->thumbnailUrl() ?? $att->url() }}" alt="{{ $att->file_name }}" class="max-w-[250px] max-h-[250px] rounded-lg object-cover cursor-pointer hover:opacity-90 transition" loading="lazy" onclick="openMediaModal('image', '{{ $att->url() }}', '{{ addslashes($att->file_name) }}')">
            @elseif($att->isVideo())
                <div class="relative cursor-pointer group" onclick="openMediaModal('video', '{{ $att->url() }}', '{{ addslashes($att->file_name) }}')">
                    <video preload="metadata" class="max-w-[280px] max-h-[250px] rounded-lg pointer-events-none">
                        <source src="{{ $att->url() }}" type="{{ $att->mime_type }}">
                    </video>
                    <div class="absolute inset-0 flex items-center justify-center bg-black/20 rounded-lg group-hover:bg-black/30 transition">
                        <svg class="w-12 h-12 text-white drop-shadow-lg" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                </div>
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
                @php
                    $ext = strtolower(pathinfo($att->file_name, PATHINFO_EXTENSION));
                    $iconColor = match(true) {
                        in_array($ext, ['pdf']) => 'text-red-500 bg-red-50',
                        in_array($ext, ['doc', 'docx']) => 'text-blue-600 bg-blue-50',
                        in_array($ext, ['xls', 'xlsx', 'csv']) => 'text-green-600 bg-green-50',
                        in_array($ext, ['ppt', 'pptx']) => 'text-orange-500 bg-orange-50',
                        in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']) => 'text-yellow-600 bg-yellow-50',
                        default => 'text-crea-secondary bg-crea-secondary/10',
                    };
                @endphp
                <a href="{{ $att->url() }}" target="_blank" class="flex items-center gap-3 p-2 rounded-lg bg-black/5 hover:bg-black/10 transition min-w-[180px]">
                    <div class="w-10 h-10 rounded-lg {{ $iconColor }} flex items-center justify-center shrink-0">
                        @if($ext === 'pdf')
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg>
                        @elseif(in_array($ext, ['doc', 'docx']))
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6zm2-5h8v1.5H8V15zm0-3h8v1.5H8V12zm0-3h5v1.5H8V9z"/></svg>
                        @elseif(in_array($ext, ['xls', 'xlsx', 'csv']))
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6zm2-5h3v1.5H8V15zm5 0h3v1.5h-3V15zM8 12h3v1.5H8V12zm5 0h3v1.5h-3V12zM8 9h3v1.5H8V9zm5 0h3v1.5h-3V9z"/></svg>
                        @elseif(in_array($ext, ['ppt', 'pptx']))
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6zm3-7h2.5c1.1 0 2-.9 2-2s-.9-2-2-2H8v7h1.5v-3zm0-2.5h2c.28 0 .5.22.5.5s-.22.5-.5.5H9v-1z"/></svg>
                        @elseif(in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']))
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-2 6h-2v2h2v2h-2v2h-2v-2h2v-2h-2v-2h2v-2h-2V8h2v2h2v2z"/></svg>
                        @else
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        @endif
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
