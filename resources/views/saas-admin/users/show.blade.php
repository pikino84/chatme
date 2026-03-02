@extends('saas-admin.layouts.admin')
@section('title', $user->name)

@section('content')
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>{{ $user->name }}</h1>
            <p>{{ $user->email }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('saas-admin.users.edit', $user) }}" class="btn btn-primary btn-sm">Edit</a>
            <a href="{{ route('saas-admin.users.index') }}" class="btn btn-sm" style="background:#e5e7eb;">Back</a>
        </div>
    </div>

    <div class="card" style="max-width:600px;">
        <table>
            <tr><td style="font-weight:500;width:160px;">ID</td><td>{{ $user->id }}</td></tr>
            <tr><td style="font-weight:500;">Name</td><td>{{ $user->name }}</td></tr>
            <tr><td style="font-weight:500;">Email</td><td>{{ $user->email }}</td></tr>
            <tr>
                <td style="font-weight:500;">Organization</td>
                <td>
                    @if($user->organization)
                        <a href="{{ route('saas-admin.organizations.show', $user->organization) }}" style="color:#2563eb;">{{ $user->organization->name }}</a>
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <td style="font-weight:500;">Roles</td>
                <td>
                    @foreach($user->roles as $role)
                        <span class="badge badge-blue">{{ $role->name }}</span>
                    @endforeach
                </td>
            </tr>
            <tr>
                <td style="font-weight:500;">Status</td>
                <td>
                    @if($user->is_active)
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="badge badge-red">Inactive</span>
                    @endif
                </td>
            </tr>
            <tr><td style="font-weight:500;">Created</td><td>{{ $user->created_at->format('M d, Y H:i') }}</td></tr>
            <tr><td style="font-weight:500;">Verified</td><td>{{ $user->email_verified_at?->format('M d, Y H:i') ?? 'Not verified' }}</td></tr>
        </table>
    </div>
@endsection
