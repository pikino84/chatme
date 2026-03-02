@if($atLimit->isNotEmpty())
    <div class="mb-6 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <div class="flex items-start gap-2">
            <span class="text-red-500 text-lg leading-none">!</span>
            <div>
                <p class="text-sm font-medium text-red-800 dark:text-red-300">Límite de uso alcanzado</p>
                <ul class="mt-1 text-xs text-red-600 dark:text-red-400 list-disc list-inside">
                    @foreach($atLimit as $item)
                        <li>{{ $item['description'] }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('billing.plans') }}" class="text-xs text-red-700 dark:text-red-300 underline mt-1 inline-block">
                    Mejora tu plan
                </a>
            </div>
        </div>
    </div>
@endif
