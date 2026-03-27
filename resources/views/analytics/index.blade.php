<x-app-layout>
    <x-slot name="header">Reportes y Analytics</x-slot>

    {{-- Period selector --}}
    <div class="mb-6 flex flex-wrap items-end gap-4">
        <form method="GET" action="{{ route('analytics.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Periodo</label>
                <select name="period" id="periodSelect" onchange="toggleCustomDates(this.value)"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    <option value="today" {{ request('period', '7d') === 'today' ? 'selected' : '' }}>Hoy</option>
                    <option value="7d" {{ request('period', '7d') === '7d' ? 'selected' : '' }}>7 dias</option>
                    <option value="30d" {{ request('period', '7d') === '30d' ? 'selected' : '' }}>30 dias</option>
                    <option value="custom" {{ request('period') === 'custom' ? 'selected' : '' }}>Personalizado</option>
                </select>
            </div>
            <div id="customDates" class="flex gap-2" style="{{ request('period') === 'custom' ? '' : 'display:none' }}">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Desde</label>
                    <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}"
                           class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Hasta</label>
                    <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}"
                           class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                </div>
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Aplicar
            </button>
        </form>

        <a href="{{ route('analytics.export', request()->all()) }}"
           class="px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors ml-auto">
            Exportar CSV
        </a>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Conversaciones</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($conversations['total']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $conversations['open'] }} abiertas &middot; {{ $conversations['closed'] }} cerradas</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Mensajes</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($messages['total']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $messages['inbound'] }} entrada &middot; {{ $messages['outbound'] }} salida</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Negocios</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($deals['total']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $deals['won'] }} ganados &middot; {{ $deals['lost'] }} perdidos</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor Ganado</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">${{ number_format($deals['won_value'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Conversion: {{ $deals['conversion_rate'] }}%</p>
        </div>
    </div>

    {{-- Secondary KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Resolucion Promedio</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {{ $conversations['avg_resolution_minutes'] ? $conversations['avg_resolution_minutes'] . ' min' : 'N/A' }}
            </p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cierre Promedio Deals</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                {{ $deals['avg_close_days'] ? $deals['avg_close_days'] . ' dias' : 'N/A' }}
            </p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SLA Cumplimiento</p>
            <p class="text-2xl font-bold {{ $sla['compliance_rate'] >= 90 ? 'text-green-600' : 'text-red-600' }} mt-1">
                {{ $sla['compliance_rate'] }}%
            </p>
            <p class="text-xs text-gray-500 mt-1">{{ $sla['breached'] }} incumplidos de {{ $sla['total'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor Total Pipeline</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">${{ number_format($deals['total_value'], 2) }}</p>
        </div>
    </div>

    {{-- Charts row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Conversation trend --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Tendencia de Conversaciones</h3>
            <div class="relative" style="height: 250px;"><canvas id="conversationTrendChart"></canvas></div>
        </div>

        {{-- Messages daily --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Volumen de Mensajes</h3>
            <div class="relative" style="height: 250px;"><canvas id="messageDailyChart"></canvas></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- By channel --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Conversaciones por Canal</h3>
            <div class="relative" style="height: 250px;"><canvas id="channelChart"></canvas></div>
        </div>

        {{-- Deal status --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Negocios por Estado</h3>
            <div class="relative" style="height: 250px;"><canvas id="dealStatusChart"></canvas></div>
        </div>
    </div>

    {{-- Agents table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Rendimiento por Agente</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agente</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Conversaciones</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cerradas</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Resolucion Prom.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($agents as $agent)
                        <tr>
                            <td class="px-5 py-3 text-sm text-gray-900 dark:text-gray-200">{{ $agent['name'] }}</td>
                            <td class="px-5 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ $agent['total_conversations'] }}</td>
                            <td class="px-5 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ $agent['closed_conversations'] }}</td>
                            <td class="px-5 py-3 text-sm text-right text-gray-600 dark:text-gray-400">
                                {{ $agent['avg_resolution_minutes'] ? $agent['avg_resolution_minutes'] . ' min' : 'N/A' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">Sin datos de agentes para este periodo</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('modals')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        function toggleCustomDates(value) {
            document.getElementById('customDates').style.display = value === 'custom' ? 'flex' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#9ca3af' : '#6b7280';
            const gridColor = isDark ? '#374151' : '#e5e7eb';

            Chart.defaults.color = textColor;
            Chart.defaults.borderColor = gridColor;

            // Conversation trend
            const trendData = @json($conversationTrend);
            new Chart(document.getElementById('conversationTrendChart'), {
                type: 'line',
                data: {
                    labels: Object.keys(trendData),
                    datasets: [{
                        label: 'Conversaciones',
                        data: Object.values(trendData),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.3,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });

            // Messages daily
            const msgData = @json($messages['daily_volume']);
            new Chart(document.getElementById('messageDailyChart'), {
                type: 'bar',
                data: {
                    labels: Object.keys(msgData),
                    datasets: [{
                        label: 'Mensajes',
                        data: Object.values(msgData),
                        backgroundColor: '#6366f1',
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });

            // By channel (doughnut)
            const channelData = @json($conversations['by_channel']);
            const channelColors = {
                whatsapp: '#25d366',
                webchat: '#6366f1',
                facebook: '#1877f2',
                instagram: '#e4405f',
                email: '#f59e0b'
            };
            new Chart(document.getElementById('channelChart'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(channelData).map(k => k.charAt(0).toUpperCase() + k.slice(1)),
                    datasets: [{
                        data: Object.values(channelData),
                        backgroundColor: Object.keys(channelData).map(k => channelColors[k] || '#9ca3af'),
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            // Deal status (doughnut)
            const dealData = @json(['Abiertos' => $deals['open'], 'Ganados' => $deals['won'], 'Perdidos' => $deals['lost']]);
            new Chart(document.getElementById('dealStatusChart'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(dealData),
                    datasets: [{
                        data: Object.values(dealData),
                        backgroundColor: ['#6366f1', '#10b981', '#ef4444'],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
