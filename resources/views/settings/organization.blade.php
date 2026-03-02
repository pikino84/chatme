<x-app-layout>
    <div class="max-w-2xl mx-auto py-8 px-4">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">Organization Settings</h2>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Organization Name</label>
                <input type="text" name="name" value="{{ old('name', $organization->name) }}" required
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                       @cannot('settings.update') disabled @endcannot>
                @error('name')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone</label>
                <select name="timezone"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                        @cannot('settings.update') disabled @endcannot>
                    <option value="">— Select timezone —</option>
                    @foreach(timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" @selected(($organization->settings['timezone'] ?? '') === $tz)>{{ $tz }}</option>
                    @endforeach
                </select>
                @error('timezone')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Logo</label>
                @if(! empty($organization->settings['logo']))
                    <div class="mb-2">
                        <img src="{{ Storage::disk('public')->url($organization->settings['logo']) }}"
                             alt="Logo" class="h-16 w-16 object-contain rounded border border-gray-200 dark:border-gray-600">
                    </div>
                @endif
                <input type="file" name="logo" accept="image/*"
                       class="text-sm text-gray-600 dark:text-gray-400"
                       @cannot('settings.update') disabled @endcannot>
                <p class="text-xs text-gray-400 mt-1">Max 1MB. JPG, PNG, SVG.</p>
                @error('logo')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            @can('settings.update')
                <div>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                        Save Settings
                    </button>
                </div>
            @endcan
        </form>

        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('settings.team') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                Manage Team Members &rarr;
            </a>
        </div>
    </div>
</x-app-layout>
