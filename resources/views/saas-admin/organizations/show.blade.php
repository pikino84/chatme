@extends('saas-admin.layouts.admin')
@section('title', $organization->name)

@section('content')
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>{{ $organization->name }}</h1>
            <p>{{ $organization->slug }}.{{ config('app.base_domain') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('saas-admin.organizations.edit', $organization) }}" class="btn btn-primary btn-sm">Edit</a>
            @if($organization->status === 'active')
                <form method="POST" action="{{ route('saas-admin.organizations.suspend', $organization) }}" onsubmit="return confirm('Suspend this organization?')">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">Suspend</button>
                </form>
            @else
                <form method="POST" action="{{ route('saas-admin.organizations.activate', $organization) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">Activate</button>
                </form>
            @endif
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Status</div>
            <div class="stat-value" style="font-size:1.25rem;">
                @if($organization->status === 'active')
                    <span class="badge badge-green">Active</span>
                @elseif($organization->status === 'suspended')
                    <span class="badge badge-red">Suspended</span>
                @else
                    <span class="badge badge-yellow">{{ ucfirst($organization->status) }}</span>
                @endif
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Users</div>
            <div class="stat-value">{{ $organization->users_count }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Branches</div>
            <div class="stat-value">{{ $organization->branches_count }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Subscription</div>
            <div class="stat-value" style="font-size:1rem;">
                @if($subscription)
                    <span class="badge badge-{{ $subscription->status === 'active' ? 'green' : ($subscription->status === 'trialing' ? 'blue' : 'yellow') }}">
                        {{ ucfirst($subscription->status) }}
                    </span>
                    <br><small style="color:#6b7280;">{{ $subscription->plan->name }} ({{ $subscription->billing_cycle }})</small>
                @else
                    <span class="badge badge-gray">None</span>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Users</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @foreach($user->roles as $role)
                            <span class="badge badge-blue">{{ $role->name }}</span>
                        @endforeach
                    </td>
                    <td>{{ $user->created_at->format('M d, Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <a href="{{ route('saas-admin.organizations.index') }}" style="color:#6b7280;text-decoration:none;font-size:0.875rem;">&larr; Back to organizations</a>
    </div>
@endsection
