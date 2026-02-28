@extends('saas-admin.layouts.admin')
@section('title', 'New Alert')

@section('content')
    <div class="page-header">
        <h1>Create Alert</h1>
    </div>

    <div class="card" style="max-width:600px;">
        <form method="POST" action="{{ route('saas-admin.alerts.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label">Type</label>
                <select name="type" class="form-select" required>
                    <option value="info" {{ old('type') === 'info' ? 'selected' : '' }}>Info</option>
                    <option value="warning" {{ old('type') === 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="critical" {{ old('type') === 'critical' ? 'selected' : '' }}>Critical</option>
                    <option value="maintenance" {{ old('type') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                </select>
                @error('type') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Scope</label>
                <select name="organization_id" class="form-select">
                    <option value="">Global (all organizations)</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}" {{ old('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                    @endforeach
                </select>
                @error('organization_id') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-input" value="{{ old('title') }}" required>
                @error('title') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-textarea" rows="4" required>{{ old('message') }}</textarea>
                @error('message') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="flex gap-2">
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Starts At (optional)</label>
                    <input type="datetime-local" name="starts_at" class="form-input" value="{{ old('starts_at') }}">
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Ends At (optional)</label>
                    <input type="datetime-local" name="ends_at" class="form-input" value="{{ old('ends_at') }}">
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Create Alert</button>
                <a href="{{ route('saas-admin.alerts.index') }}" class="btn" style="background:#e5e7eb;">Cancel</a>
            </div>
        </form>
    </div>
@endsection
