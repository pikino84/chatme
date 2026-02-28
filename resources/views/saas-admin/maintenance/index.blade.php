@extends('saas-admin.layouts.admin')
@section('title', 'Maintenance Mode')

@section('content')
    <div class="page-header">
        <h1>Maintenance Mode</h1>
        <p>Enable or disable maintenance mode per organization</p>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Maintenance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($organizations as $org)
                <tr>
                    <td>{{ $org->name }}</td>
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
                    <td>
                        @if($org->in_maintenance)
                            <span class="badge badge-yellow">In Maintenance</span>
                        @else
                            <span class="badge badge-green">Online</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('saas-admin.maintenance.toggle', $org) }}">
                            @csrf
                            @if($org->in_maintenance)
                                <button type="submit" class="btn btn-success btn-sm">Disable Maintenance</button>
                            @else
                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Enable maintenance mode for {{ $org->name }}?')">Enable Maintenance</button>
                            @endif
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
