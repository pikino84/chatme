<x-app-layout>
    <div class="max-w-3xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Facturación y Suscripción</h2>
            <a href="{{ route('billing.plans') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                Ver Planes &rarr;
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        @include('billing.partials.limit-banner', ['atLimit' => $atLimit])

        @if(! $subscription)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
                <p class="text-gray-500 dark:text-gray-400 mb-4">Sin suscripción activa.</p>
                <a href="{{ route('billing.plans') }}"
                   class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    Elegir un Plan
                </a>
            </div>
        @else
            {{-- Current Plan Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $plan->name }}</h3>
                        @if($plan->description)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $plan->description }}</p>
                        @endif
                    </div>
                    <span class="text-xs px-2.5 py-1 rounded-full font-medium
                        @if($subscription->isActive()) bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300
                        @elseif($subscription->isTrialing()) bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                        @elseif($subscription->isCanceled()) bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300
                        @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300
                        @endif">
                        {{ ucfirst($subscription->status) }}
                    </span>
                </div>

                <dl class="text-sm space-y-2 mb-4">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Ciclo de Facturación</dt>
                        <dd class="text-gray-900 dark:text-gray-100 capitalize">{{ $subscription->billing_cycle }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Precio</dt>
                        <dd class="text-gray-900 dark:text-gray-100">
                            ${{ number_format(($subscription->billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly) / 100, 2) }}
                            / {{ $subscription->billing_cycle === 'yearly' ? 'año' : 'mes' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Inicio</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $subscription->starts_at->format('M d, Y') }}</dd>
                    </div>
                    @if($subscription->ends_at)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Renueva</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $subscription->ends_at->format('M d, Y') }}</dd>
                        </div>
                    @endif
                    @if($subscription->isTrialing() && $subscription->trial_ends_at)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Fin del Periodo de Prueba</dt>
                            <dd class="text-blue-600 dark:text-blue-400">{{ $subscription->trial_ends_at->format('M d, Y') }} ({{ $subscription->trial_ends_at->diffForHumans() }})</dd>
                        </div>
                    @endif
                    @if($subscription->isCanceled() && $subscription->grace_period_ends_at)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Acceso Hasta</dt>
                            <dd class="text-red-500">{{ $subscription->grace_period_ends_at->format('M d, Y') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Features --}}
            @if($features->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase mb-3">Características</h4>
                    <div class="space-y-2">
                        @foreach($features as $f)
                            <div class="flex items-center gap-2 text-sm">
                                @if($f['enabled'])
                                    <span class="text-green-500">&#10003;</span>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">&#10007;</span>
                                @endif
                                <span class="text-gray-700 dark:text-gray-300">{{ $f['description'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Usage Meters --}}
            @if($limits->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase mb-3">Uso</h4>
                    @foreach($limits as $l)
                        @include('billing.partials.usage-meter', [
                            'label' => $l['description'],
                            'usage' => $l['usage'],
                            'limit' => $l['limit'],
                            'percentage' => $l['percentage'],
                            'isUnlimited' => $l['isUnlimited'],
                        ])
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
