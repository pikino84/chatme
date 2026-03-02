<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SaaS Admin') - ChatMe Admin</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #1f2937; }
        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: #111827; color: #fff; padding: 1.5rem 0; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 0 1.5rem 1.5rem; font-size: 1.25rem; font-weight: 700; border-bottom: 1px solid #374151; }
        .sidebar-nav { padding: 1rem 0; flex: 1; }
        .sidebar-link { display: block; padding: 0.625rem 1.5rem; color: #9ca3af; text-decoration: none; font-size: 0.875rem; transition: all 0.15s; }
        .sidebar-link:hover, .sidebar-link.active { color: #fff; background: #1f2937; }
        .main-content { margin-left: 240px; padding: 2rem; }
        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 { font-size: 1.5rem; font-weight: 600; }
        .page-header p { color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem; }
        .card { background: #fff; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem; margin-bottom: 1rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #fff; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.25rem; }
        .stat-label { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 2px solid #e5e7eb; }
        td { padding: 0.75rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.875rem; }
        tr:hover td { background: #f9fafb; }
        .badge { display: inline-block; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; cursor: pointer; border: none; transition: all 0.15s; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-success { background: #059669; color: #fff; }
        .btn-success:hover { background: #047857; }
        .btn-warning { background: #d97706; color: #fff; }
        .btn-warning:hover { background: #b45309; }
        .btn-sm { padding: 0.25rem 0.75rem; font-size: 0.75rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.375rem; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .alert { padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .user-info { padding: 1rem 1.5rem; border-top: 1px solid #374151; font-size: 0.75rem; color: #9ca3af; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 0.5rem; }
        .mt-4 { margin-top: 1rem; }
        .mb-4 { margin-bottom: 1rem; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand">ChatMe Admin</div>
        <nav class="sidebar-nav">
            <a href="{{ route('saas-admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('saas-admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('saas-admin.organizations.index') }}" class="sidebar-link {{ request()->routeIs('saas-admin.organizations.*') ? 'active' : '' }}">Organizations</a>
            <a href="{{ route('saas-admin.users.index') }}" class="sidebar-link {{ request()->routeIs('saas-admin.users.*') ? 'active' : '' }}">Users</a>
            <a href="{{ route('saas-admin.plans.index') }}" class="sidebar-link {{ request()->routeIs('saas-admin.plans.*') ? 'active' : '' }}">Plans</a>
            <a href="{{ route('saas-admin.subscriptions.index') }}" class="sidebar-link {{ request()->routeIs('saas-admin.subscriptions.*') ? 'active' : '' }}">Subscriptions</a>
            <a href="{{ route('saas-admin.usage.index') }}" class="sidebar-link {{ request()->routeIs('saas-admin.usage.*') ? 'active' : '' }}">Usage</a>
            <a href="{{ route('saas-admin.alerts.index') }}" class="sidebar-link {{ request()->routeIs('saas-admin.alerts.*') ? 'active' : '' }}">Alerts</a>
            <a href="{{ route('saas-admin.channel-forms.index') }}" class="sidebar-link {{ request()->routeIs('saas-admin.channel-forms.*') ? 'active' : '' }}">Channel Forms</a>
            <a href="{{ route('saas-admin.maintenance.index') }}" class="sidebar-link {{ request()->routeIs('saas-admin.maintenance.*') ? 'active' : '' }}">Maintenance</a>
            <a href="/horizon" class="sidebar-link" target="_blank">Horizon</a>
        </nav>
        <div class="user-info">
            {{ auth()->user()->name }}<br>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:0;font-size:0.75rem;">Logout</button>
            </form>
        </div>
    </aside>

    <main class="main-content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
