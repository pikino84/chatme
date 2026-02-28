@extends('saas-admin.layouts.admin')

@section('title', 'Assign Form to Channel')

@section('content')
<div class="page-header">
    <h1>Assign Form to Channel</h1>
    <p>Select a webchat channel and a template</p>
</div>

<div class="card">
    @if($channels->isEmpty())
        <p style="color:#6b7280;">All webchat channels already have a form assigned, or no webchat channels exist.</p>
        <a href="{{ route('saas-admin.channel-forms.index') }}" class="btn mt-4" style="background:#e5e7eb;">Back</a>
    @else
        <form method="POST" action="{{ route('saas-admin.channel-forms.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="channel_id">Channel</label>
                <select name="channel_id" id="channel_id" class="form-select" required>
                    <option value="">-- Select channel --</option>
                    @foreach($channels as $channel)
                        <option value="{{ $channel->id }}" {{ old('channel_id') == $channel->id ? 'selected' : '' }}>
                            {{ $channel->name }} ({{ $channel->organization->name ?? 'N/A' }})
                        </option>
                    @endforeach
                </select>
                @error('channel_id')
                    <p style="color:#dc2626;font-size:0.8rem;margin-top:0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="template_key">Template</label>
                <select name="template_key" id="template_key" class="form-select" required>
                    <option value="">-- Select template --</option>
                    @foreach($templates as $key => $template)
                        <option value="{{ $key }}" {{ old('template_key') == $key ? 'selected' : '' }}>
                            {{ $template['name'] ?? $key }}
                        </option>
                    @endforeach
                </select>
                @error('template_key')
                    <p style="color:#dc2626;font-size:0.8rem;margin-top:0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div id="template-preview" class="mt-4" style="display:none;">
                <h3 style="margin-bottom:0.5rem;">Template Preview</h3>
                <pre id="template-json" style="background:#f3f4f6;padding:1rem;border-radius:0.375rem;overflow-x:auto;font-size:0.8rem;line-height:1.5;"></pre>
            </div>

            <div class="flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Assign Form</button>
                <a href="{{ route('saas-admin.channel-forms.index') }}" class="btn" style="background:#e5e7eb;">Cancel</a>
            </div>
        </form>

        <script>
            const templates = @json($templates);
            document.getElementById('template_key').addEventListener('change', function() {
                const preview = document.getElementById('template-preview');
                const json = document.getElementById('template-json');
                if (this.value && templates[this.value]) {
                    json.textContent = JSON.stringify(templates[this.value], null, 2);
                    preview.style.display = 'block';
                } else {
                    preview.style.display = 'none';
                }
            });
        </script>
    @endif
</div>
@endsection
