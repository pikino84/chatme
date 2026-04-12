<x-app-layout>
    <div class="max-w-2xl mx-auto py-8 px-4">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">Configuración de Organización</h2>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre de la Organización</label>
                <input type="text" name="name" value="{{ old('name', $organization->name) }}" required
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                       @cannot('settings.update') disabled @endcannot>
                @error('name')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div x-data="{
                    search: '',
                    open: false,
                    selected: '{{ $organization->settings['timezone'] ?? '' }}',
                    timezones: @js(array_merge(
                        ['America/Cancun'],
                        array_filter(timezone_identifiers_list(), fn($tz) => $tz !== 'America/Cancun')
                    )),
                    get filtered() {
                        if (!this.search) return this.timezones;
                        const q = this.search.toLowerCase();
                        return this.timezones.filter(tz => tz.toLowerCase().includes(q));
                    }
                 }"
                 x-on:click.outside="open = false">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Zona Horaria</label>
                <input type="hidden" name="timezone" :value="selected">
                <div class="relative">
                    <input type="text"
                           x-model="search"
                           x-on:focus="open = true"
                           x-on:input="open = true"
                           :placeholder="selected || '— Buscar zona horaria —'"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                           @cannot('settings.update') disabled @endcannot>
                    <div x-show="open && filtered.length > 0" x-cloak
                         class="absolute z-20 mt-1 w-full max-h-60 overflow-y-auto bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg">
                        <template x-for="tz in filtered" :key="tz">
                            <button type="button"
                                    x-text="tz"
                                    x-on:click="selected = tz; search = ''; open = false"
                                    :class="selected === tz ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-200'"
                                    class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                            </button>
                        </template>
                    </div>
                </div>
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
                <p class="text-xs text-gray-400 mt-1">Máx 1MB. JPG, PNG, SVG.</p>
                @error('logo')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Business Hours --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Horario de Atención</h3>
                @php
                    $bh = $organization->settings['business_hours'] ?? [];
                    $schedule = $bh['schedule'] ?? [];
                    $days = ['monday' => 'Lunes', 'tuesday' => 'Martes', 'wednesday' => 'Miércoles', 'thursday' => 'Jueves', 'friday' => 'Viernes', 'saturday' => 'Sábado', 'sunday' => 'Domingo'];
                @endphp

                <label class="flex items-center gap-2 mb-4 cursor-pointer">
                    <input type="checkbox" name="business_hours[enabled]" value="1"
                           @checked(!empty($bh['enabled']))
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                           onchange="document.getElementById('bh-grid').classList.toggle('hidden', !this.checked)">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Habilitar horario de atención</span>
                </label>

                <div id="bh-grid" class="{{ empty($bh['enabled']) ? 'hidden' : '' }} space-y-2">
                    @foreach($days as $key => $label)
                        @php $d = $schedule[$key] ?? ['enabled' => false, 'start' => '09:00', 'end' => '18:00']; @endphp
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 w-28 shrink-0">
                                <input type="checkbox" name="business_hours[schedule][{{ $key }}][enabled]" value="1"
                                       @checked(!empty($d['enabled']))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                       onchange="this.closest('.flex').querySelectorAll('input[type=time]').forEach(function(i){i.classList.toggle('opacity-50',!event.target.checked)})">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                            </label>
                            <input type="time" name="business_hours[schedule][{{ $key }}][start]" value="{{ $d['start'] ?? '09:00' }}"
                                   class="text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 {{ empty($d['enabled']) ? 'opacity-50' : '' }}">
                            <span class="text-xs text-gray-400">a</span>
                            <input type="time" name="business_hours[schedule][{{ $key }}][end]" value="{{ $d['end'] ?? '18:00' }}"
                                   class="text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 {{ empty($d['enabled']) ? 'opacity-50' : '' }}">
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Auto-Responses --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Respuestas Automáticas</h3>
                @php $ar = $organization->settings['auto_responses'] ?? []; @endphp

                {{-- Welcome --}}
                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <label class="flex items-center gap-2 mb-2 cursor-pointer">
                        <input type="checkbox" name="auto_responses[welcome][enabled]" value="1"
                               @checked(!empty($ar['welcome']['enabled']))
                               class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Mensaje de bienvenida</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Se envía automáticamente en la primera conversación con un nuevo contacto.</p>
                    <textarea name="auto_responses[welcome][message]" rows="2" maxlength="1000"
                              placeholder="¡Hola! Bienvenido a nuestra empresa. ¿En qué podemos ayudarte?"
                              class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">{{ $ar['welcome']['message'] ?? '' }}</textarea>
                </div>

                {{-- Out of hours --}}
                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <label class="flex items-center gap-2 mb-2 cursor-pointer">
                        <input type="checkbox" name="auto_responses[out_of_hours][enabled]" value="1"
                               @checked(!empty($ar['out_of_hours']['enabled']))
                               class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Fuera de horario</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Se envía cuando un cliente escribe fuera del horario de atención configurado.</p>
                    <textarea name="auto_responses[out_of_hours][message]" rows="2" maxlength="1000"
                              placeholder="Gracias por contactarnos. Nuestro horario es L-V 9am-6pm. Te responderemos en cuanto estemos disponibles."
                              class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">{{ $ar['out_of_hours']['message'] ?? '' }}</textarea>
                </div>

                {{-- Agent timeout --}}
                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <label class="flex items-center gap-2 mb-2 cursor-pointer">
                        <input type="checkbox" name="auto_responses[agent_timeout][enabled]" value="1"
                               @checked(!empty($ar['agent_timeout']['enabled']))
                               class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Agente sin responder</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Se envía si un agente no responde al cliente dentro del tiempo configurado.</p>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs text-gray-500">Tiempo de espera:</span>
                        <input type="number" name="auto_responses[agent_timeout][timeout_minutes]"
                               value="{{ $ar['agent_timeout']['timeout_minutes'] ?? 10 }}"
                               min="1" max="120"
                               class="w-20 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <span class="text-xs text-gray-500">minutos</span>
                    </div>
                    <textarea name="auto_responses[agent_timeout][message]" rows="2" maxlength="1000"
                              placeholder="Disculpa la demora, un agente te atenderá en breve."
                              class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">{{ $ar['agent_timeout']['message'] ?? '' }}</textarea>
                </div>
            </div>

            @can('settings.update')
                <div>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                        Guardar Configuración
                    </button>
                </div>
            @endcan
        </form>

        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('settings.team') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                Gestionar Miembros del Equipo &rarr;
            </a>
        </div>
    </div>
</x-app-layout>
