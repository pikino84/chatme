<x-app-layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Planes Disponibles</h2>
            <a href="{{ route('billing.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Volver a Facturación
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($plans as $plan)
                @php
                    $isCurrent = $currentPlan && $currentPlan->id === $plan->id;
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 flex flex-col {{ $isCurrent ? 'ring-2 ring-indigo-500' : '' }}">
                    {{-- Header --}}
                    <div class="mb-4">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $plan->name }}</h3>
                            @if($isCurrent)
                                <span class="text-[10px] px-2 py-0.5 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 rounded-full font-medium">Actual</span>
                            @endif
                        </div>
                        @if($plan->description)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $plan->description }}</p>
                        @endif
                    </div>

                    {{-- Price --}}
                    <div class="mb-4">
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            ${{ number_format($plan->price_monthly / 100, 2) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">/ mes</span>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            or ${{ number_format($plan->price_yearly / 100, 2) }} / año
                        </div>
                    </div>

                    {{-- Features --}}
                    <div class="flex-1 mb-4 space-y-2">
                        @foreach($plan->featureValues->sortBy('feature.code') as $fv)
                            <div class="flex items-center gap-2 text-sm">
                                @if($fv->feature->isBoolean())
                                    @if($fv->value === 'true')
                                        <span class="text-green-500">&#10003;</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">&#10007;</span>
                                    @endif
                                    <span class="text-gray-700 dark:text-gray-300">{{ $fv->feature->description }}</span>
                                @else
                                    <span class="text-indigo-500">&#8226;</span>
                                    <span class="text-gray-700 dark:text-gray-300">
                                        {{ $fv->feature->description }}:
                                        <span class="font-medium">{{ $fv->isUnlimited() ? 'Ilimitado' : number_format((int) $fv->value) }}</span>
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Action --}}
                    <div class="mt-auto">
                        @if($isCurrent)
                            <span class="block w-full text-center px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-md">
                                Plan Actual
                            </span>
                        @else
                            <form method="POST" action="{{ route('billing.change-plan') }}">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <button type="submit"
                                        class="block w-full text-center px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                                    Cambiar a {{ $plan->name }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
