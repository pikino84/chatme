<div class="mb-4">
    <div class="flex justify-between text-sm mb-1">
        <span class="text-gray-700 dark:text-gray-300">{{ $label }}</span>
        @if($isUnlimited)
            <span class="text-gray-500 dark:text-gray-400">Ilimitado</span>
        @else
            <span class="text-gray-500 dark:text-gray-400">{{ number_format($usage) }} / {{ number_format($limit) }}</span>
        @endif
    </div>
    @unless($isUnlimited)
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div class="h-2 rounded-full transition-all
                @if($percentage >= 90) bg-red-500
                @elseif($percentage >= 70) bg-yellow-500
                @else bg-green-500
                @endif"
                 style="width: {{ $percentage }}%"></div>
        </div>
    @endunless
</div>
