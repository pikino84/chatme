@extends('saas-admin.layouts.admin')
@section('title', 'Alerts')

@section('content')
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>Alerts</h1>
            <p>System alerts and notifications</p>
        </div>
        <a href="{{ route('saas-admin.alerts.create') }}" class="btn btn-primary">New Alert</a>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('saas-admin.alerts.index') }}" class="flex gap-2 items-center">
            <label style="font-size:0.875rem;">
                <input type="checkbox" name="active_only" value="1" {{ request('active_only') ? 'checked' : '' }} onchange="this.form.submit()">
                Active only
            </label>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Scope</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($alerts as $alert)
                <tr>
                    <td>
                        <span class="badge badge-{{ $alert->type === 'critical' ? 'red' : ($alert->type === 'warning' ? 'yellow' : ($alert->type === 'maintenance' ? 'blue' : 'gray')) }}">
                            {{ ucfirst($alert->type) }}
                        </span>
                    </td>
                    <td>{{ $alert->title }}</td>
                    <td>{{ $alert->isGlobal() ? 'Global' : $alert->organization->name }}</td>
                    <td>
                        @if($alert->isResolved())
                            <span class="badge badge-green">Resolved</span>
                        @elseif($alert->is_active)
                            <span class="badge badge-red">Active</span>
                        @else
                            <span class="badge badge-gray">Inactive</span>
                        @endif
                    </td>
                    <td>{{ $alert->created_at->format('M d, Y H:i') }}</td>
                    <td class="flex gap-2">
                        <a href="{{ route('saas-admin.alerts.edit', $alert) }}" class="btn btn-primary btn-sm">Edit</a>
                        @if(!$alert->isResolved() && $alert->is_active)
                            <form method="POST" action="{{ route('saas-admin.alerts.resolve', $alert) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">Resolve</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('saas-admin.alerts.destroy', $alert) }}" onsubmit="return confirm('Delete this alert?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#6b7280;">No alerts.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $alerts->links() }}</div>
    </div>
@endsection
