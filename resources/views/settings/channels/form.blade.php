<x-app-layout>
    <div class="max-w-2xl mx-auto py-8 px-4">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
            {{ $channel ? 'Editar Canal' : 'Agregar Canal' }}
        </h2>

        @if(session('error'))
            <div class="mb-4 px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm rounded">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST"
              action="{{ $channel ? route('settings.channels.update', $channel) : route('settings.channels.store') }}"
              class="space-y-6"
              id="channel-form">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del Canal</label>
                <input type="text" name="name" value="{{ old('name', $channel->name ?? '') }}" required
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                @error('name')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            @if($brands->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Marca</label>
                    <select name="brand_id"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <option value="">Sin marca (global)</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(old('brand_id', $channel?->brand_id) == $brand->id)>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Asocia este canal a una marca para filtrar conversaciones e IA por marca.</p>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                @if($channel)
                    <input type="text" value="{{ ucfirst($channel->type) }}" disabled
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400 text-sm bg-gray-100 dark:bg-gray-800">
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4" id="type-buttons">
                        @foreach(['whatsapp' => 'WhatsApp', 'webchat' => 'Webchat', 'facebook' => 'Facebook', 'instagram' => 'Instagram'] as $value => $label)
                            <label class="channel-type-btn relative flex items-center justify-center px-4 py-2.5 rounded-md border cursor-pointer text-sm font-medium transition-colors border-gray-500 text-gray-200 hover:border-gray-300"
                                   data-type="{{ $value }}">
                                <input type="radio" name="type" value="{{ $value }}" class="sr-only"
                                       {{ old('type') === $value ? 'checked' : '' }}>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('type')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            {{-- WhatsApp Config --}}
            <div id="config-whatsapp" class="channel-config space-y-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700" style="display: none;">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Configuraci&oacute;n de WhatsApp</h3>

                @php
                    $waConfig = $channel ? ($channel->configuration ?? []) : [];
                @endphp

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Phone Number ID</label>
                    <input type="text" name="phone_number_id" value="{{ old('phone_number_id', $waConfig['phone_number_id'] ?? '') }}"
                           disabled
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @error('phone_number_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">WABA ID</label>
                    <input type="text" name="waba_id" value="{{ old('waba_id', $waConfig['waba_id'] ?? '') }}"
                           disabled
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @error('waba_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Access Token</label>
                    <input type="password" name="access_token"
                           disabled
                           placeholder="{{ $channel && !empty($waConfig['access_token']) ? '********' . substr($waConfig['access_token'], -8) : '' }}"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @if($channel)
                        <p class="text-xs text-gray-400 mt-1">Dejar en blanco para mantener el valor actual.</p>
                    @endif
                    @error('access_token') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Verify Token</label>
                    <input type="text" name="verify_token" value="{{ old('verify_token', $waConfig['verify_token'] ?? '') }}"
                           disabled
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @error('verify_token') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">App Secret</label>
                    <input type="password" name="app_secret"
                           disabled
                           placeholder="{{ $channel && !empty($waConfig['app_secret']) ? '********' . substr($waConfig['app_secret'], -8) : '' }}"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @if($channel)
                        <p class="text-xs text-gray-400 mt-1">Dejar en blanco para mantener el valor actual.</p>
                    @endif
                    @error('app_secret') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">N&uacute;mero de tel&eacute;fono visible</label>
                    <input type="text" name="display_phone" value="{{ old('display_phone', $waConfig['display_phone'] ?? '') }}"
                           disabled
                           placeholder="+52 1 555 123 4567"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @error('display_phone') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Webchat Config --}}
            <div id="config-webchat" class="channel-config space-y-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700" style="display: none;">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Configuraci&oacute;n de Webchat</h3>

                @php
                    $wcConfig = $channel ? ($channel->configuration ?? []) : [];
                    $origins = implode("\n", $wcConfig['allowed_origins'] ?? []);
                @endphp

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Or&iacute;genes permitidos (uno por l&iacute;nea)</label>
                    <textarea name="allowed_origins" rows="3"
                              disabled
                              placeholder="https://ejemplo.com&#10;https://tienda.ejemplo.com"
                              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">{{ old('allowed_origins', $origins) }}</textarea>
                    @error('allowed_origins') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Facebook Config --}}
            <div id="config-facebook" class="channel-config space-y-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700" style="display: none;">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Configuraci&oacute;n de Facebook Messenger</h3>

                @php
                    $fbConfig = $channel ? ($channel->configuration ?? []) : [];
                @endphp

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Page ID</label>
                    <input type="text" name="page_id" value="{{ old('page_id', $fbConfig['page_id'] ?? '') }}"
                           disabled
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @error('page_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Page Access Token</label>
                    <input type="password" name="page_access_token"
                           disabled
                           placeholder="{{ $channel && !empty($fbConfig['page_access_token']) ? '********' . substr($fbConfig['page_access_token'], -8) : '' }}"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @if($channel)
                        <p class="text-xs text-gray-400 mt-1">Dejar en blanco para mantener el valor actual.</p>
                    @endif
                    @error('page_access_token') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">App Secret</label>
                    <input type="password" name="app_secret"
                           disabled
                           placeholder="{{ $channel && !empty($fbConfig['app_secret']) ? '********' . substr($fbConfig['app_secret'], -8) : '' }}"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @if($channel)
                        <p class="text-xs text-gray-400 mt-1">Dejar en blanco para mantener el valor actual.</p>
                    @endif
                    @error('app_secret') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Verify Token</label>
                    <input type="text" name="verify_token" value="{{ old('verify_token', $fbConfig['verify_token'] ?? '') }}"
                           disabled
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @error('verify_token') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Instagram Config --}}
            <div id="config-instagram" class="channel-config space-y-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700" style="display: none;">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Configuraci&oacute;n de Instagram</h3>

                @php
                    $igConfig = $channel ? ($channel->configuration ?? []) : [];
                @endphp

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Instagram Account ID</label>
                    <input type="text" name="instagram_account_id" value="{{ old('instagram_account_id', $igConfig['instagram_account_id'] ?? '') }}"
                           disabled
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @error('instagram_account_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Page ID (p&aacute;gina vinculada)</label>
                    <input type="text" name="page_id" value="{{ old('page_id', $igConfig['page_id'] ?? '') }}"
                           disabled
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @error('page_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Page Access Token</label>
                    <input type="password" name="page_access_token"
                           disabled
                           placeholder="{{ $channel && !empty($igConfig['page_access_token']) ? '********' . substr($igConfig['page_access_token'], -8) : '' }}"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @if($channel)
                        <p class="text-xs text-gray-400 mt-1">Dejar en blanco para mantener el valor actual.</p>
                    @endif
                    @error('page_access_token') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">App Secret</label>
                    <input type="password" name="app_secret"
                           disabled
                           placeholder="{{ $channel && !empty($igConfig['app_secret']) ? '********' . substr($igConfig['app_secret'], -8) : '' }}"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @if($channel)
                        <p class="text-xs text-gray-400 mt-1">Dejar en blanco para mantener el valor actual.</p>
                    @endif
                    @error('app_secret') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Verify Token</label>
                    <input type="text" name="verify_token" value="{{ old('verify_token', $igConfig['verify_token'] ?? '') }}"
                           disabled
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @error('verify_token') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    {{ $channel ? 'Actualizar Canal' : 'Crear Canal' }}
                </button>
                <a href="{{ $channel ? route('settings.channels.show', $channel) : route('settings.channels') }}"
                   class="text-sm text-gray-500 dark:text-gray-400 hover:underline">Cancelar</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var buttons = document.querySelectorAll('.channel-type-btn');
            var configs = document.querySelectorAll('.channel-config');
            var activeClass = 'border-indigo-500 bg-indigo-600 text-white';
            var inactiveClass = 'border-gray-500 text-gray-200 hover:border-gray-300';

            function selectType(type) {
                // Update buttons
                buttons.forEach(function(btn) {
                    var radio = btn.querySelector('input[type="radio"]');
                    if (btn.dataset.type === type) {
                        radio.checked = true;
                        btn.className = btn.className.replace(inactiveClass, '').trim();
                        activeClass.split(' ').forEach(function(c) { btn.classList.add(c); });
                        inactiveClass.split(' ').forEach(function(c) { btn.classList.remove(c); });
                    } else {
                        radio.checked = false;
                        activeClass.split(' ').forEach(function(c) { btn.classList.remove(c); });
                        inactiveClass.split(' ').forEach(function(c) { btn.classList.add(c); });
                    }
                });

                // Show/hide config sections and toggle disabled
                configs.forEach(function(config) {
                    var configType = config.id.replace('config-', '');
                    var inputs = config.querySelectorAll('input, textarea, select');
                    if (configType === type) {
                        config.style.display = '';
                        inputs.forEach(function(input) { input.disabled = false; });
                    } else {
                        config.style.display = 'none';
                        inputs.forEach(function(input) { input.disabled = true; });
                    }
                });
            }

            // Click handlers
            buttons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    selectType(btn.dataset.type);
                });
            });

            // Initialize from old input or edit mode
            var initialType = '{{ old('type', $channel->type ?? '') }}';
            if (initialType) {
                selectType(initialType);
            }
        });
    </script>
    @endpush
</x-app-layout>
