@extends('saas-admin.layouts.admin')
@section('title', 'Users')

@section('content')
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>Users</h1>
            <p>Manage all platform users</p>
        </div>
        <a href="{{ route('saas-admin.users.create') }}" class="btn btn-primary">+ New User</a>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('saas-admin.users.index') }}" class="flex gap-2 items-center" style="flex-wrap:wrap;">
            <input type="text" name="search" class="form-input" placeholder="Search name or email..." value="{{ request('search') }}" style="max-width:250px;">
            <select name="role" class="form-select" style="max-width:160px;">
                <option value="">All roles</option>
                @foreach($roles as $r)
                    <option value="{{ $r }}" {{ request('role') === $r ? 'selected' : '' }}>{{ $r }}</option>
                @endforeach
            </select>
            <select name="organization_id" class="form-select" style="max-width:200px;">
                <option value="">All organizations</option>
                @foreach($organizations as $org)
                    <option value="{{ $org->id }}" {{ request('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            @if(request('search') || request('role') || request('organization_id'))
                <a href="{{ route('saas-admin.users.index') }}" class="btn btn-sm" style="background:#e5e7eb;">Clear</a>
            @endif
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Organization</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td><a href="{{ route('saas-admin.users.show', $user) }}" style="color:#2563eb;text-decoration:none;">{{ $user->name }}</a></td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->organization?->name ?? '—' }}</td>
                    <td>
                        @foreach($user->roles as $role)
                            <span class="badge badge-blue">{{ $role->name }}</span>
                        @endforeach
                    </td>
                    <td>
                        @if($user->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('saas-admin.users.edit', $user) }}" class="btn btn-primary btn-sm">Edit</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:#6b7280;">No users found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>
@endsection
