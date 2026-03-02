@extends('saas-admin.layouts.admin')
@section('title', 'New Organization')

@section('content')
    <div class="page-header">
        <h1>Create Organization</h1>
    </div>

    <div class="card" style="max-width:600px;">
        <form method="POST" action="{{ route('saas-admin.organizations.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-input" value="{{ old('name') }}" required>
                @error('name') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Slug</label>
                <input type="text" name="slug" class="form-input" value="{{ old('slug') }}" required>
                @error('slug') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="trial" {{ old('status') === 'trial' ? 'selected' : '' }}>Trial</option>
                    <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>
                @error('status') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Create Organization</button>
                <a href="{{ route('saas-admin.organizations.index') }}" class="btn" style="background:#e5e7eb;">Cancel</a>
            </div>
        </form>
    </div>
@endsection
