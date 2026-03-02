@extends('saas-admin.layouts.admin')
@section('title', 'Subscriptions')

@section('content')
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>Subscriptions</h1>
            <p>All organization subscriptions</p>
        </div>
        <a href="{{ route('saas-admin.subscriptions.create') }}" class="btn btn-primary">+ New Subscription</a>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('saas-admin.subscriptions.index') }}" class="flex gap-2 items-center">
            <select name="status" class="form-select" style="max-width:160px;">
                <option value="">All statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="trialing" {{ request('status') === 'trialing' ? 'selected' : '' }}>Trialing</option>
                <option value="canceled" {{ request('status') === 'canceled' ? 'selected' : '' }}>Canceled</option>
                <option value="past_due" {{ request('status') === 'past_due' ? 'selected' : '' }}>Past Due</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            @if(request('status'))
                <a href="{{ route('saas-admin.subscriptions.index') }}" class="btn btn-sm" style="background:#e5e7eb;">Clear</a>
            @endif
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Cycle</th>
                    <th>Starts</th>
                    <th>Ends</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $sub)
                <tr>
                    <td>{{ $sub->organization->name ?? 'N/A' }}</td>
                    <td>{{ $sub->plan->name ?? 'N/A' }}</td>
                    <td>
                        <span class="badge badge-{{ $sub->status === 'active' ? 'green' : ($sub->status === 'trialing' ? 'blue' : ($sub->status === 'canceled' ? 'yellow' : 'red')) }}">
                            {{ ucfirst(str_replace('_', ' ', $sub->status)) }}
                        </span>
                    </td>
                    <td>{{ ucfirst($sub->billing_cycle) }}</td>
                    <td>{{ $sub->starts_at?->format('M d, Y') ?? '-' }}</td>
                    <td>{{ $sub->ends_at?->format('M d, Y') ?? '-' }}</td>
                    <td>
                        <a href="{{ route('saas-admin.subscriptions.show', $sub->id) }}" class="btn btn-primary btn-sm">Manage</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:#6b7280;">No subscriptions found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $subscriptions->links() }}</div>
    </div>
@endsection
