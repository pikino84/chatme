@extends('saas-admin.layouts.admin')
@section('title', 'Edit ' . $organization->name)

@section('content')
    <div class="page-header">
        <h1>Edit Organization</h1>
        <p>{{ $organization->name }}</p>
    </div>

    <div class="card" style="max-width:600px;">
        <form method="POST" action="{{ route('saas-admin.organizations.update', $organization) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-input" value="{{ old('name', $organization->name) }}" required>
                @error('name') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Slug (subdomain)</label>
                @if($organization->canChangeSlug())
                    <input type="text" name="slug" class="form-input" value="{{ old('slug', $organization->slug) }}">
                    <small style="color:#6b7280;">Leave empty to auto-generate from name. Editable because this org has no users, branches, or subscriptions.</small>
                @else
                    <input type="text" class="form-input" value="{{ $organization->slug }}" disabled style="background:#f3f4f6;">
                    <small style="color:#6b7280;">Cannot change slug — this organization has active users, branches, or subscriptions that depend on the subdomain.</small>
                @endif
                @error('slug') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('saas-admin.organizations.show', $organization) }}" class="btn" style="background:#e5e7eb;">Cancel</a>
            </div>
        </form>
    </div>
@endsection
