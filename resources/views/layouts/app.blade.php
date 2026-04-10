<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ChatMe') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <x-banner />

        <div x-data="{ sidebarOpen: window.innerWidth >= 1024 }" x-on:resize.window="sidebarOpen = window.innerWidth >= 1024" class="min-h-screen bg-gray-100 dark:bg-gray-900 flex">

            {{-- Overlay (mobile) --}}
            <div x-show="sidebarOpen" x-on:click="sidebarOpen = false"
                 class="fixed inset-0 z-20 bg-black/50 lg:hidden"
                 x-transition:enter="transition-opacity ease-linear duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-linear duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 x-cloak></div>

            {{-- Sidebar --}}
            <aside x-show="sidebarOpen"
                   x-transition:enter="transition ease-in-out duration-200 transform"
                   x-transition:enter-start="-translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in-out duration-200 transform"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="-translate-x-full"
                   class="fixed inset-y-0 left-0 z-30 w-64 bg-crea-primary flex flex-col lg:static lg:translate-x-0 lg:z-auto"
                   x-cloak>

                {{-- Brand --}}
                <div class="flex items-center justify-between h-16 px-5 border-b border-crea-primary-dark">
                    <a href="{{ route('dashboard') }}" class="text-white text-lg font-bold tracking-wide">ChatMe</a>
                    <button x-on:click="sidebarOpen = false" class="text-crea-secondary-light hover:text-white lg:hidden">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Navigation --}}
                <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    @php
                        $links = [
                            ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Inicio', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"/>'],
                            ['route' => 'inbox', 'match' => 'inbox*', 'label' => 'Bandeja', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>'],
                            ['route' => 'deals.board', 'match' => 'deals.*', 'label' => 'Negocios', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>'],
                            ['route' => 'campaigns.index', 'match' => 'campaigns.*', 'label' => 'Campañas', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>'],
                            ['route' => 'contacts.index', 'match' => 'contacts.*', 'label' => 'Contactos', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
                            ['route' => 'kb.articles', 'match' => 'kb.*', 'label' => 'Base de Conocimiento', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>'],
                            ['route' => 'analytics.index', 'match' => 'analytics.*', 'label' => 'Reportes', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'],
                        ];
                    @endphp

                    @foreach($links as $link)
                        <a href="{{ route($link['route']) }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs($link['match']) ? 'bg-crea-secondary text-white' : 'text-gray-300 hover:bg-crea-primary-light hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $link['icon'] !!}</svg>
                            {{ $link['label'] }}
                            @if($link['route'] === 'inbox')
                                <span id="inbox-badge" class="hidden ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"></span>
                            @endif
                        </a>
                    @endforeach

                    @can('settings.update')
                        <div class="pt-4 mt-4 border-t border-crea-primary-dark">
                            <p class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Admin</p>

                            <a href="{{ route('settings.channels') }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('settings.channels*') ? 'bg-crea-secondary text-white' : 'text-gray-300 hover:bg-crea-primary-light hover:text-white' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                                Canales
                            </a>

                            <a href="{{ route('billing.index') }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('billing.*') ? 'bg-crea-secondary text-white' : 'text-gray-300 hover:bg-crea-primary-light hover:text-white' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                Facturaci&oacute;n
                            </a>

                            <a href="{{ route('settings.show') }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('settings.*') ? 'bg-crea-secondary text-white' : 'text-gray-300 hover:bg-crea-primary-light hover:text-white' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Configuraci&oacute;n
                            </a>

                            <a href="{{ route('settings.team') }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('settings.team*') ? 'bg-crea-secondary text-white' : 'text-gray-300 hover:bg-crea-primary-light hover:text-white' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                Equipo
                            </a>

                            <a href="{{ route('pipelines.index') }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('pipelines.*') ? 'bg-crea-secondary text-white' : 'text-gray-300 hover:bg-crea-primary-light hover:text-white' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                Pipelines
                            </a>

                            <a href="{{ route('tags.index') }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('tags.*') ? 'bg-crea-secondary text-white' : 'text-gray-300 hover:bg-crea-primary-light hover:text-white' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                                Etiquetas
                            </a>
                        </div>
                    @endcan
                </nav>

                {{-- User footer --}}
                <div class="border-t border-crea-primary-dark p-4" x-data="{ open: false }">
                    <button x-on:click="open = !open" class="flex items-center gap-3 w-full text-left">
                        <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold shrink-0">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ Auth::user()->email }}</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    </button>
                    <div x-show="open" x-on:click.outside="open = false" x-transition class="mt-2 space-y-1" x-cloak>
                        <a href="{{ route('profile.show') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-crea-primary-light hover:text-white transition-colors">Perfil</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-left px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-crea-primary-light hover:text-white transition-colors">Cerrar Sesi&oacute;n</button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- Main content --}}
            <div class="flex-1 flex flex-col min-h-screen min-w-0">

                {{-- Top bar --}}
                <header class="sticky top-0 z-10 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 h-16 flex items-center px-4 sm:px-6 gap-4">
                    <button x-on:click="sidebarOpen = !sidebarOpen" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    @if (isset($header))
                        <div class="text-lg font-semibold text-gray-800 dark:text-gray-200 truncate">
                            {{ $header }}
                        </div>
                    @endif
                </header>

                {{-- Page content --}}
                <main class="flex-1 p-0 sm:p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @stack('modals')

        {{-- Toast notification container --}}
        <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm" style="pointer-events: none;"></div>

        @livewireScripts
        @stack('scripts')

        {{-- Global notification system --}}
        <script>
        (function() {
            var originalTitle = document.title;
            var notifPollUrl = '{{ route("notifications.poll") }}';
            var lastPollTime = new Date().toISOString();
            var prevUnread = 0;
            var soundEnabled = localStorage.getItem('chatme_sound') !== 'off';
            var notifAudio = null;

            // Notification sound (WhatsApp-style double pop via Web Audio API)
            window.playNotifSound = function() {
                if (!soundEnabled) return;
                try {
                    if (!notifAudio) {
                        var ctx = new (window.AudioContext || window.webkitAudioContext)();
                        notifAudio = ctx;
                    }
                    var ctx = notifAudio;
                    if (ctx.state === 'suspended') ctx.resume();
                    var t = ctx.currentTime;

                    // First pop - lower tone
                    var osc1 = ctx.createOscillator();
                    var gain1 = ctx.createGain();
                    osc1.connect(gain1);
                    gain1.connect(ctx.destination);
                    osc1.frequency.setValueAtTime(600, t);
                    osc1.frequency.exponentialRampToValueAtTime(900, t + 0.06);
                    osc1.type = 'sine';
                    gain1.gain.setValueAtTime(0.25, t);
                    gain1.gain.exponentialRampToValueAtTime(0.001, t + 0.1);
                    osc1.start(t);
                    osc1.stop(t + 0.1);

                    // Second pop - higher tone (after short pause)
                    var osc2 = ctx.createOscillator();
                    var gain2 = ctx.createGain();
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.frequency.setValueAtTime(800, t + 0.12);
                    osc2.frequency.exponentialRampToValueAtTime(1200, t + 0.18);
                    osc2.type = 'sine';
                    gain2.gain.setValueAtTime(0.25, t + 0.12);
                    gain2.gain.exponentialRampToValueAtTime(0.001, t + 0.22);
                    osc2.start(t + 0.12);
                    osc2.stop(t + 0.22);
                } catch (e) {}
            }

            // Toast notification
            function showToast(title, body, url) {
                var container = document.getElementById('toast-container');
                if (!container) return;

                var toast = document.createElement('div');
                toast.style.pointerEvents = 'auto';
                toast.className = 'bg-white shadow-lg rounded-xl p-3 border border-gray-200 flex items-start gap-3 animate-slide-in cursor-pointer transform transition-all duration-300';
                toast.innerHTML = '<div class="w-8 h-8 rounded-full bg-crea-secondary/10 flex items-center justify-center shrink-0"><svg class="w-4 h-4 text-crea-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg></div>' +
                    '<div class="flex-1 min-w-0"><p class="text-sm font-medium text-gray-900 truncate">' + escToast(title) + '</p><p class="text-xs text-gray-500 truncate">' + escToast(body) + '</p></div>' +
                    '<button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600 shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>';

                if (url) {
                    toast.addEventListener('click', function(e) {
                        if (e.target.closest('button')) return;
                        window.location.href = url;
                    });
                }

                container.appendChild(toast);

                // Auto-remove after 5s
                setTimeout(function() {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(function() { toast.remove(); }, 300);
                }, 5000);
            }

            function escToast(str) {
                if (!str) return '';
                var d = document.createElement('div');
                d.textContent = str;
                return d.innerHTML;
            }

            // Update badge
            function updateBadge(count) {
                var badge = document.getElementById('inbox-badge');
                if (!badge) return;
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.remove('hidden');
                    document.title = '(' + count + ') ' + originalTitle;
                } else {
                    badge.classList.add('hidden');
                    document.title = originalTitle;
                }
            }

            // Request browser notification permission
            if ('Notification' in window && Notification.permission === 'default') {
                // Request on first user interaction
                document.addEventListener('click', function requestNotif() {
                    Notification.requestPermission();
                    document.removeEventListener('click', requestNotif);
                }, { once: true });
            }

            // Browser push notification
            function sendBrowserNotif(title, body, url) {
                if (!('Notification' in window) || Notification.permission !== 'granted') return;
                try {
                    var notif = new Notification(title, {
                        body: body,
                        icon: '/favicon.ico',
                        tag: 'chatme-msg',
                        renotify: true,
                    });
                    if (url) {
                        notif.onclick = function() { window.focus(); window.location.href = url; };
                    }
                } catch (e) {}
            }

            // Poll notifications
            function pollNotifications() {
                fetch(notifPollUrl + '?since=' + encodeURIComponent(lastPollTime), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var unread = data.unread_conversations || 0;
                    updateBadge(unread);

                    // New messages since last poll
                    if (data.recent_unread && data.recent_unread.length > 0) {
                        // Get current conversation ID from URL
                        var viewingMatch = window.location.pathname.match(/\/inbox\/conversations\/(\d+)/);
                        var viewingConvId = viewingMatch ? parseInt(viewingMatch[1]) : null;

                        // Filter out the conversation currently being viewed
                        var alertConvs = data.recent_unread.filter(function(conv) {
                            return conv.id !== viewingConvId;
                        });

                        if (alertConvs.length > 0) {
                            playNotifSound();
                        }

                        alertConvs.forEach(function(conv) {
                            var msg = conv.unread_count + ' mensaje' + (conv.unread_count > 1 ? 's' : '') + ' nuevo' + (conv.unread_count > 1 ? 's' : '');
                            showToast(conv.contact_name, msg, '/inbox/conversations/' + conv.id);

                            if (document.hidden) {
                                sendBrowserNotif('ChatMe - ' + conv.contact_name, msg, '/inbox/conversations/' + conv.id);
                            }

                            // Highlight conversation in inbox list
                            var convEl = document.querySelector('[data-conv-id="' + conv.id + '"]');
                            if (convEl) {
                                convEl.classList.add('bg-crea-secondary/5', 'border-l-2', 'border-crea-secondary');
                                var nameEl = convEl.querySelector('.text-sm');
                                if (nameEl) nameEl.classList.add('font-bold');
                                // Update preview text and time
                                if (conv.last_body) {
                                    var previewEl = convEl.querySelector('.text-xs.text-gray-500');
                                    if (previewEl) previewEl.textContent = conv.last_body.substring(0, 50);
                                }
                                var timeEl = convEl.querySelector('.text-xs.text-gray-400');
                                if (timeEl) timeEl.textContent = 'ahora';
                            }
                        });
                    }

                    prevUnread = unread;
                    if (data.server_time) lastPollTime = data.server_time;
                })
                .catch(function() {});
            }

            // Initial poll + interval
            pollNotifications();
            setInterval(pollNotifications, 10000);

            // Mark conversation as read when viewing it
            var currentConvMatch = window.location.pathname.match(/\/inbox\/conversations\/(\d+)/);
            if (currentConvMatch) {
                fetch('/inbox/conversations/' + currentConvMatch[1] + '/read', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                }).catch(function() {});
            }
        })();
        </script>

        <style>
            @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            .animate-slide-in { animation: slideIn 0.3s ease-out; }
        </style>
    </body>
</html>
