<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SaaS Admin') - ChatMe Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #1f2937; }

        /* Sidebar - Navy primary */
        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: #1e3a5f; color: #fff; padding: 0; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 1.25rem 1.5rem; font-size: 1.25rem; font-weight: 700; border-bottom: 1px solid #152a45; display: flex; align-items: center; gap: 0.75rem; }
        .sidebar-brand-icon { width: 2rem; height: 2rem; background: rgba(255,255,255,0.1); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; }
        .sidebar-nav { padding: 1rem 0.75rem; flex: 1; display: flex; flex-direction: column; gap: 0.125rem; }
        .sidebar-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 1rem; color: rgba(255,255,255,0.65); text-decoration: none; font-size: 0.875rem; border-radius: 0.5rem; transition: all 0.15s; }
        .sidebar-link:hover { color: #fff; background: #2d4a6f; }
        .sidebar-link.active { color: #fff; background: #0891b2; }

        /* Main content */
        .main-content { margin-left: 240px; padding: 2rem; }
        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 { font-size: 1.5rem; font-weight: 600; }
        .page-header p { color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem; }

        /* Cards */
        .card { background: #fff; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.12); padding: 1.5rem; margin-bottom: 1rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #fff; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem; border-left: 4px solid #1e3a5f; display: flex; align-items: center; gap: 1rem; }
        .stat-card.border-blue { border-left-color: #3b82f6; }
        .stat-card.border-green { border-left-color: #059669; }
        .stat-card.border-red { border-left-color: #dc2626; }
        .stat-card.border-orange { border-left-color: #d97706; }
        .stat-card.border-secondary { border-left-color: #0891b2; }
        .stat-icon { width: 3rem; height: 3rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-icon svg { width: 1.5rem; height: 1.5rem; }
        .stat-icon.icon-primary { background: rgba(30,58,95,0.1); color: #1e3a5f; }
        .stat-icon.icon-blue { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .stat-icon.icon-green { background: rgba(5,150,105,0.1); color: #059669; }
        .stat-icon.icon-red { background: rgba(220,38,38,0.1); color: #dc2626; }
        .stat-icon.icon-orange { background: rgba(217,119,6,0.1); color: #d97706; }
        .stat-icon.icon-secondary { background: rgba(8,145,178,0.1); color: #0891b2; }
        .stat-label { font-size: 0.875rem; color: #6b7280; font-weight: 500; }
        .stat-value { font-size: 1.5rem; font-weight: 700; margin-top: 0.125rem; color: #111827; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 2px solid #e5e7eb; }
        td { padding: 0.75rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.875rem; }
        tr:hover td { background: #f9fafb; }

        /* Badges */
        .badge { display: inline-block; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }

        /* Buttons - Gradient CTA */
        .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; cursor: pointer; border: none; transition: all 0.15s; }
        .btn-primary { background: linear-gradient(135deg, #0891b2 0%, #7c3aed 100%); color: #fff; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-success { background: #059669; color: #fff; }
        .btn-success:hover { background: #047857; }
        .btn-warning { background: #d97706; color: #fff; }
        .btn-warning:hover { background: #b45309; }
        .btn-sm { padding: 0.25rem 0.75rem; font-size: 0.75rem; }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.375rem; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; font-family: inherit; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #0891b2; box-shadow: 0 0 0 3px rgba(8,145,178,0.15); }

        /* Alerts */
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* User footer */
        .user-info { padding: 1rem 1.25rem; border-top: 1px solid #152a45; display: flex; align-items: center; gap: 0.75rem; }
        .user-avatar { width: 2.25rem; height: 2.25rem; border-radius: 9999px; background: #0891b2; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.875rem; flex-shrink: 0; }
        .user-details { flex: 1; min-width: 0; }
        .user-name { font-size: 0.875rem; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 0.75rem; color: rgba(255,255,255,0.5); }
        .user-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
        .user-action-btn { background: none; border: none; color: rgba(255,255,255,0.5); cursor: pointer; padding: 0.25rem; border-radius: 0.375rem; transition: color 0.15s; display: flex; align-items: center; justify-content: center; }
        .user-action-btn:hover { color: #fff; }

        /* Utilities */
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
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
            ChatMe Admin
        </div>
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
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div class="user-details">
                <div class="user-name">{{ auth()->user()->name }}</div>
                <div class="user-role">admin</div>
            </div>
            <div class="user-actions">
                <a href="{{ route('saas-admin.dashboard') }}" class="user-action-btn" title="Configuración">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </a>
                <form method="POST" action="{{ route('logout') }}" style="display:inline">
                    @csrf
                    <button type="submit" class="user-action-btn" title="Cerrar sesión">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </form>
            </div>
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
