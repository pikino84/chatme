@extends('saas-admin.layouts.admin')
@section('title', 'Edit Alert')

@section('content')
    <div class="page-header">
        <h1>Edit Alert</h1>
    </div>

    <div class="card" style="max-width:600px;">
        <form method="POST" action="{{ route('saas-admin.alerts.update', $alert) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label">Type</label>
                <select name="type" class="form-select" required>
                    @foreach(['info', 'warning', 'critical', 'maintenance'] as $type)
                        <option value="{{ $type }}" {{ old('type', $alert->type) === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Scope</label>
                <select name="organization_id" class="form-select">
                    <option value="">Global (all organizations)</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}" {{ old('organization_id', $alert->organization_id) == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-input" value="{{ old('title', $alert->title) }}" required>
            </div>

            <div class="form-group">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-textarea" rows="4" required>{{ old('message', $alert->message) }}</textarea>
            </div>

            <div class="form-group">
                <label style="font-size:0.875rem;">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $alert->is_active) ? 'checked' : '' }}>
                    Active
                </label>
            </div>

            <div class="flex gap-2">
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Starts At</label>
                    <input type="datetime-local" name="starts_at" class="form-input" value="{{ old('starts_at', $alert->starts_at?->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Ends At</label>
                    <input type="datetime-local" name="ends_at" class="form-input" value="{{ old('ends_at', $alert->ends_at?->format('Y-m-d\TH:i')) }}">
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('saas-admin.alerts.index') }}" class="btn" style="background:#e5e7eb;">Cancel</a>
            </div>
        </form>
    </div>
@endsection
