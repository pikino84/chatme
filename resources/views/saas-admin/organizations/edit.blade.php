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
                <label class="form-label">Slug</label>
                <input type="text" name="slug" class="form-input" value="{{ old('slug', $organization->slug) }}" required>
                @error('slug') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('saas-admin.organizations.show', $organization) }}" class="btn" style="background:#e5e7eb;">Cancel</a>
            </div>
        </form>
    </div>
@endsection
