@extends('saas-admin.layouts.admin')
@section('title', $user ? 'Edit ' . $user->name : 'New User')

@section('content')
    <div class="page-header">
        <h1>{{ $user ? 'Edit User' : 'Create User' }}</h1>
        @if($user)
            <p>{{ $user->email }}</p>
        @endif
    </div>

    <div class="card" style="max-width:600px;">
        <form method="POST" action="{{ $user ? route('saas-admin.users.update', $user) : route('saas-admin.users.store') }}">
            @csrf
            @if($user) @method('PUT') @endif

            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-input" value="{{ old('name', $user?->name) }}" required>
                @error('name') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" value="{{ old('email', $user?->email) }}" required>
                @error('email') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Password {{ $user ? '(leave blank to keep current)' : '' }}</label>
                <input type="password" name="password" class="form-input" {{ $user ? '' : 'required' }}>
                @error('password') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Organization</label>
                <select name="organization_id" class="form-select">
                    <option value="">None (platform-level user)</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}" {{ old('organization_id', $user?->organization_id) == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                    @endforeach
                </select>
                @error('organization_id') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="">Select role</option>
                    @foreach($roles as $r)
                        <option value="{{ $r }}" {{ old('role', $user?->roles->first()?->name) === $r ? 'selected' : '' }}>{{ $r }}</option>
                    @endforeach
                </select>
                @error('role') <small style="color:#dc2626;">{{ $message }}</small> @enderror
            </div>

            @if($user)
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                        <span class="form-label" style="margin:0;">Active</span>
                    </label>
                </div>
            @endif

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">{{ $user ? 'Save Changes' : 'Create User' }}</button>
                <a href="{{ $user ? route('saas-admin.users.show', $user) : route('saas-admin.users.index') }}" class="btn" style="background:#e5e7eb;">Cancel</a>
            </div>
        </form>

        @if($user && $user->id !== auth()->id())
            <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid #e5e7eb;">
                <form method="POST" action="{{ route('saas-admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user permanently?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Delete User</button>
                </form>
            </div>
        @endif
    </div>
@endsection
