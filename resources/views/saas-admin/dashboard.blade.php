@extends('saas-admin.layouts.admin')
@section('title', 'Dashboard')

@section('content')
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Overview of your SaaS platform</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <div class="stat-label">Total Organizations</div>
                <div class="stat-value">{{ $stats['total_organizations'] }}</div>
            </div>
        </div>

        <div class="stat-card border-green">
            <div class="stat-icon icon-green">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="stat-label">Active</div>
                <div class="stat-value" style="color:#059669">{{ $stats['active_organizations'] }}</div>
            </div>
        </div>

        <div class="stat-card border-red">
            <div class="stat-icon icon-red">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </div>
            <div>
                <div class="stat-label">Suspended</div>
                <div class="stat-value" style="color:#dc2626">{{ $stats['suspended_organizations'] }}</div>
            </div>
        </div>

        <div class="stat-card border-blue">
            <div class="stat-icon icon-blue">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <div>
                <div class="stat-label">Active Subscriptions</div>
                <div class="stat-value">{{ $stats['active_subscriptions'] }}</div>
            </div>
        </div>

        <div class="stat-card border-orange">
            <div class="stat-icon icon-orange">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            </div>
            <div>
                <div class="stat-label">Canceled</div>
                <div class="stat-value" style="color:#d97706">{{ $stats['canceled_subscriptions'] }}</div>
            </div>
        </div>

        <div class="stat-card border-secondary">
            <div class="stat-icon icon-secondary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="stat-label">Est. Monthly Revenue</div>
                <div class="stat-value" style="color:#0891b2">${{ number_format($stats['monthly_revenue'] / 100, 2) }}</div>
            </div>
        </div>

        <div class="stat-card {{ $stats['active_alerts'] > 0 ? 'border-red' : 'border-green' }}">
            <div class="stat-icon {{ $stats['active_alerts'] > 0 ? 'icon-red' : 'icon-green' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <div>
                <div class="stat-label">Active Alerts</div>
                <div class="stat-value" style="color:{{ $stats['active_alerts'] > 0 ? '#dc2626' : '#059669' }}">{{ $stats['active_alerts'] }}</div>
            </div>
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
                    <td><a href="{{ route('saas-admin.organizations.show', $org) }}" style="color:#0891b2;text-decoration:none;">{{ $org->name }}</a></td>
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
