@extends('saas-admin.layouts.admin')
@section('title', 'Organizations')

@section('content')
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>Organizations</h1>
            <p>Manage all tenant organizations</p>
        </div>
        <a href="{{ route('saas-admin.organizations.create') }}" class="btn btn-primary">+ New Organization</a>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('saas-admin.organizations.index') }}" class="flex gap-2 items-center">
            <input type="text" name="search" class="form-input" placeholder="Search by name or slug..." value="{{ request('search') }}" style="max-width:300px;">
            <select name="status" class="form-select" style="max-width:160px;">
                <option value="">All statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                <option value="trial" {{ request('status') === 'trial' ? 'selected' : '' }}>Trial</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            @if(request('search') || request('status'))
                <a href="{{ route('saas-admin.organizations.index') }}" class="btn btn-sm" style="background:#e5e7eb;">Clear</a>
            @endif
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($organizations as $org)
                <tr>
                    <td>{{ $org->id }}</td>
                    <td><a href="{{ route('saas-admin.organizations.show', $org) }}" style="color:#2563eb;text-decoration:none;">{{ $org->name }}</a></td>
                    <td>{{ $org->slug }}</td>
                    <td>
                        @if($org->status === 'active')
                            <span class="badge badge-green">Active</span>
                        @elseif($org->status === 'suspended')
                            <span class="badge badge-red">Suspended</span>
                        @else
                            <span class="badge badge-yellow">{{ ucfirst($org->status) }}</span>
                        @endif
                    </td>
                    <td>{{ $org->created_at->format('M d, Y') }}</td>
                    <td>
                        <a href="{{ route('saas-admin.organizations.show', $org) }}" class="btn btn-primary btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#6b7280;">No organizations found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $organizations->links() }}</div>
    </div>
@endsection
