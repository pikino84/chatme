@extends('saas-admin.layouts.admin')
@section('title', 'Dashboard')

@section('content')
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Overview of your SaaS platform</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Organizations</div>
            <div class="stat-value">{{ $stats['total_organizations'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active</div>
            <div class="stat-value" style="color:#059669">{{ $stats['active_organizations'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Suspended</div>
            <div class="stat-value" style="color:#dc2626">{{ $stats['suspended_organizations'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Subscriptions</div>
            <div class="stat-value">{{ $stats['active_subscriptions'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Canceled</div>
            <div class="stat-value" style="color:#d97706">{{ $stats['canceled_subscriptions'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Est. Monthly Revenue</div>
            <div class="stat-value">${{ number_format($stats['monthly_revenue'] / 100, 2) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Alerts</div>
            <div class="stat-value" style="color:{{ $stats['active_alerts'] > 0 ? '#dc2626' : '#059669' }}">{{ $stats['active_alerts'] }}</div>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Recent Organizations</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentOrgs as $org)
                <tr>
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
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
