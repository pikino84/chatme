@extends('saas-admin.layouts.admin')

@section('title', 'Form Details')

@section('content')
<div class="page-header">
    <h1>Form Details</h1>
    <p>Channel: {{ $channelForm->channel->name ?? 'N/A' }} — {{ $channelForm->channel->organization->name ?? 'N/A' }}</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Template</div>
        <div class="stat-value" style="font-size:1.25rem;">{{ $channelForm->template_key ?? 'Custom' }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Status</div>
        <div class="stat-value" style="font-size:1.25rem;">
            @if($channelForm->is_active)
                <span class="badge badge-green">Active</span>
            @else
                <span class="badge badge-gray">Inactive</span>
            @endif
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Fields</div>
        <div class="stat-value" style="font-size:1.25rem;">{{ count($channelForm->schema['fields'] ?? []) }}</div>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom:1rem;">Schema (read-only)</h3>
    <pre style="background:#f3f4f6;padding:1rem;border-radius:0.375rem;overflow-x:auto;font-size:0.8rem;line-height:1.5;">{{ json_encode($channelForm->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</div>

<div class="flex gap-2 mt-4">
    <form method="POST" action="{{ route('saas-admin.channel-forms.toggle', $channelForm) }}">
        @csrf
        <button type="submit" class="btn {{ $channelForm->is_active ? 'btn-warning' : 'btn-success' }}">
            {{ $channelForm->is_active ? 'Deactivate' : 'Activate' }}
        </button>
    </form>
    <form method="POST" action="{{ route('saas-admin.channel-forms.destroy', $channelForm) }}" onsubmit="return confirm('Remove this form?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">Delete</button>
    </form>
    <a href="{{ route('saas-admin.channel-forms.index') }}" class="btn" style="background:#e5e7eb;">Back</a>
</div>
@endsection
