<x-app-layout>
    <div class="max-w-2xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Conectar Canal</h2>
            <a href="{{ route('settings.channels') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Canales</a>
        </div>

        {{-- Step Indicator --}}
        <div class="flex items-center justify-center mb-8" id="step-indicator">
            <div class="flex items-center gap-0">
                @foreach([1 => 'Tipo', 2 => 'Configurar', 3 => 'Verificar', 4 => 'Listo'] as $num => $label)
                    <div class="flex items-center">
                        <div class="flex flex-col items-center">
                            <div class="step-circle w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-colors
                                {{ $num === 1 ? 'border-indigo-500 bg-indigo-500 text-white' : 'border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500' }}"
                                data-step="{{ $num }}">
                                {{ $num }}
                            </div>
                            <span class="text-[10px] mt-1 text-gray-500 dark:text-gray-400">{{ $label }}</span>
                        </div>
                        @if($num < 4)
                            <div class="step-line w-12 sm:w-20 h-0.5 bg-gray-300 dark:bg-gray-600 mx-1 mb-4 transition-colors" data-after-step="{{ $num }}"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Billing Limit Banner --}}
        @if(!$canCreate)
            <div class="mb-6 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg" id="billing-banner">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <div>
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-200">L&iacute;mite de canales alcanzado</p>
                        <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">Tu plan actual permite {{ $currentCount }} canal(es). Actualiza tu plan para conectar m&aacute;s canales.</p>
                        <a href="{{ route('billing.plans') }}" class="inline-block mt-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Ver planes &rarr;</a>
                    </div>
                </div>
            </div>
        @endif

        {{-- ═══ STEP 1: Select Type ═══ --}}
        <div class="wizard-step" data-wizard-step="1">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-1">Selecciona el tipo de canal</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">&iquest;Qu&eacute; canal de comunicaci&oacute;n deseas conectar?</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="type-cards">
                {{-- WhatsApp --}}
                <button type="button" data-channel-type="whatsapp"
                        class="channel-card relative text-left p-5 rounded-lg border-2 transition-all
                            {{ $whatsappEnabled ? 'border-gray-200 dark:border-gray-700 hover:border-green-400 dark:hover:border-green-500 cursor-pointer' : 'border-gray-200 dark:border-gray-700 opacity-50 cursor-not-allowed' }}"
                        {{ !$whatsappEnabled ? 'disabled' : '' }}>
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-2xl">&#128172;</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">WhatsApp</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Conecta tu n&uacute;mero de WhatsApp Business API para recibir y enviar mensajes.</p>
                    @if(!$whatsappEnabled)
                        <span class="absolute top-2 right-2 text-[10px] px-2 py-0.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 rounded-full font-medium">Upgrade</span>
                    @endif
                </button>

                {{-- Facebook --}}
                <button type="button" data-channel-type="facebook"
                        class="channel-card text-left p-5 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-blue-400 dark:hover:border-blue-500 transition-all cursor-pointer">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-2xl">&#128279;</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Facebook</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Recibe mensajes de Facebook Messenger desde tu p&aacute;gina de Facebook.</p>
                </button>

                {{-- Instagram --}}
                <button type="button" data-channel-type="instagram"
                        class="channel-card text-left p-5 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-pink-400 dark:hover:border-pink-500 transition-all cursor-pointer">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-2xl">&#128247;</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Instagram</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Conecta tu cuenta de Instagram para gestionar DMs desde ChatMe.</p>
                </button>

                {{-- Webchat --}}
                <button type="button" data-channel-type="webchat"
                        class="channel-card relative text-left p-5 rounded-lg border-2 transition-all
                            {{ $webchatEnabled ? 'border-gray-200 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-500 cursor-pointer' : 'border-gray-200 dark:border-gray-700 opacity-50 cursor-not-allowed' }}"
                        {{ !$webchatEnabled ? 'disabled' : '' }}>
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-2xl">&#128187;</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Webchat</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">A&ntilde;ade un widget de chat en vivo a tu sitio web.</p>
                    @if(!$webchatEnabled)
                        <span class="absolute top-2 right-2 text-[10px] px-2 py-0.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 rounded-full font-medium">Upgrade</span>
                    @endif
                </button>
            </div>
        </div>

        {{-- ═══ STEP 2: Configuration ═══ --}}
        <div class="wizard-step" data-wizard-step="2" style="display:none">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-1" id="step2-title">Configuraci&oacute;n</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6" id="step2-desc"></p>

            <div class="space-y-5">
                {{-- Common: Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del canal *</label>
                    <input type="text" id="field-name" placeholder="Ej: WhatsApp Ventas"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    <p class="text-xs text-gray-400 mt-1">Un nombre interno para identificar este canal.</p>
                </div>

                {{-- Common: Brand --}}
                @if($brands->isNotEmpty())
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Marca (opcional)</label>
                        <select id="field-brand_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                            <option value="">Sin marca (global)</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- ── WhatsApp Fields ── --}}
                <div class="type-fields space-y-4" data-type-fields="whatsapp" style="display:none">
                    <div class="p-3 bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 rounded-lg">
                        <p class="text-xs text-green-700 dark:text-green-300">Necesitas una app de Meta con WhatsApp Business API configurado. Obt&eacute;n estos datos en <strong>Meta Developer Console &gt; Tu App &gt; WhatsApp &gt; API Setup</strong>.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Phone Number ID *</label>
                        <input type="text" id="field-phone_number_id" placeholder="Ej: 1234567890"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">Se encuentra en WhatsApp &gt; API Setup &gt; Phone number ID.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">WABA ID *</label>
                        <input type="text" id="field-waba_id" placeholder="Ej: 9876543210"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">WhatsApp Business Account ID. Se encuentra en WhatsApp &gt; API Setup.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">App ID</label>
                        <input type="text" id="field-app_id" placeholder="Ej: 123456789"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">ID de tu app de Meta. Necesario para plantillas con multimedia. Se encuentra en Meta Developers &gt; Tu App.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Access Token *</label>
                        <input type="password" id="field-access_token" placeholder="EAA..."
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">Token permanente generado en tu app de Meta. Usa un System User token para producci&oacute;n.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Verify Token *</label>
                        <input type="text" id="field-verify_token" placeholder="mi-token-secreto"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">Una cadena secreta que t&uacute; defines. Se usar&aacute; para verificar el webhook en Meta.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">App Secret *</label>
                        <input type="password" id="field-app_secret" placeholder="abc123..."
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">En Meta Developer Console &gt; Tu App &gt; Settings &gt; Basic &gt; App Secret.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Tel&eacute;fono visible *</label>
                        <input type="text" id="field-display_phone" placeholder="+52 1 555 123 4567"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">N&uacute;mero de tel&eacute;fono que ven tus clientes.</p>
                    </div>
                </div>

                {{-- ── Facebook Fields ── --}}
                <div class="type-fields space-y-4" data-type-fields="facebook" style="display:none">
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <p class="text-xs text-blue-700 dark:text-blue-300">Necesitas una app de Meta con Messenger configurado. Obt&eacute;n estos datos en <strong>Meta Developer Console &gt; Tu App &gt; Messenger &gt; Settings</strong>.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Page ID *</label>
                        <input type="text" id="field-fb-page_id" placeholder="Ej: 123456789"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">ID de tu p&aacute;gina de Facebook. Se encuentra en la configuraci&oacute;n de tu p&aacute;gina.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Page Access Token *</label>
                        <input type="password" id="field-fb-page_access_token" placeholder="EAA..."
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">Genera un token de p&aacute;gina en Messenger &gt; Settings &gt; Access Tokens.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">App Secret *</label>
                        <input type="password" id="field-fb-app_secret" placeholder="abc123..."
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Verify Token *</label>
                        <input type="text" id="field-fb-verify_token" placeholder="mi-token-secreto"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    </div>
                </div>

                {{-- ── Instagram Fields ── --}}
                <div class="type-fields space-y-4" data-type-fields="instagram" style="display:none">
                    <div class="p-3 bg-pink-50 dark:bg-pink-900/10 border border-pink-200 dark:border-pink-800 rounded-lg">
                        <p class="text-xs text-pink-700 dark:text-pink-300">Tu cuenta de Instagram debe ser tipo Business/Creator y estar vinculada a una p&aacute;gina de Facebook. Configura en <strong>Meta Developer Console &gt; Tu App &gt; Instagram</strong>.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Instagram Account ID *</label>
                        <input type="text" id="field-ig-instagram_account_id" placeholder="Ej: 17841400000"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        <p class="text-xs text-gray-400 mt-1">ID de la cuenta de Instagram Business. Se obtiene via la API de Facebook.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Page ID (p&aacute;gina vinculada) *</label>
                        <input type="text" id="field-ig-page_id" placeholder="Ej: 123456789"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Page Access Token *</label>
                        <input type="password" id="field-ig-page_access_token" placeholder="EAA..."
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">App Secret *</label>
                        <input type="password" id="field-ig-app_secret" placeholder="abc123..."
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Verify Token *</label>
                        <input type="text" id="field-ig-verify_token" placeholder="mi-token-secreto"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    </div>
                </div>

                {{-- ── Webchat Fields ── --}}
                <div class="type-fields space-y-4" data-type-fields="webchat" style="display:none">
                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <p class="text-xs text-gray-600 dark:text-gray-400">El widget de webchat se instala con un snippet de c&oacute;digo en tu sitio web. Opcionalmente puedes restringir los dominios permitidos.</p>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Or&iacute;genes permitidos (opcional)</label>
                        <textarea id="field-wc-allowed_origins" rows="3"
                                  placeholder="https://ejemplo.com&#10;https://tienda.ejemplo.com"
                                  class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"></textarea>
                        <p class="text-xs text-gray-400 mt-1">Uno por l&iacute;nea. Si lo dejas vac&iacute;o, se aceptar&aacute;n todos los or&iacute;genes.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mt-8">
                <button type="button" id="btn-back-1" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Atr&aacute;s</button>
                <button type="button" id="btn-next-2"
                        class="px-5 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition {{ !$canCreate ? 'opacity-50 cursor-not-allowed' : '' }}"
                        {{ !$canCreate ? 'disabled' : '' }}>
                    Verificar Conexi&oacute;n &rarr;
                </button>
            </div>
        </div>

        {{-- ═══ STEP 3: Validate ═══ --}}
        <div class="wizard-step" data-wizard-step="3" style="display:none">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-1">Verificar Conexi&oacute;n</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Estamos verificando que las credenciales sean v&aacute;lidas.</p>

            <div id="validation-loading" class="text-center py-8">
                <div class="inline-block w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">Conectando con el servidor...</p>
            </div>

            <div id="validation-success" class="p-5 bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 rounded-lg" style="display:none">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="font-medium text-green-800 dark:text-green-200" id="validation-success-title">Conexi&oacute;n exitosa</p>
                        <p class="text-sm text-green-700 dark:text-green-300 mt-1" id="validation-success-detail"></p>
                    </div>
                </div>
            </div>

            <div id="validation-error" class="p-5 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-lg" style="display:none">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="font-medium text-red-800 dark:text-red-200">Error de conexi&oacute;n</p>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-1" id="validation-error-message"></p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mt-8">
                <button type="button" id="btn-back-2" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Corregir datos</button>
                <button type="button" id="btn-save" style="display:none"
                        class="px-5 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    Guardar Canal &rarr;
                </button>
            </div>
        </div>

        {{-- ═══ STEP 4: Complete ═══ --}}
        <div class="wizard-step" data-wizard-step="4" style="display:none">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-green-100 dark:bg-green-900/20 rounded-full mb-3">
                    <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100" id="complete-title">&iexcl;Canal conectado!</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" id="complete-subtitle"></p>
            </div>

            {{-- Webhook Info (WhatsApp/Facebook/Instagram) --}}
            <div id="complete-webhook" class="space-y-4" style="display:none">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5 space-y-4">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Configura el Webhook en Meta</h4>

                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">URL del Webhook</label>
                        <div class="flex gap-2">
                            <input type="text" id="complete-webhook-url" readonly
                                   class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm bg-gray-50 dark:bg-gray-900">
                            <button type="button" data-copy-from="complete-webhook-url"
                                    class="copy-btn px-3 py-2 text-xs bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 transition">
                                Copiar
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Verify Token</label>
                        <div class="flex gap-2">
                            <input type="text" id="complete-verify-token" readonly
                                   class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm bg-gray-50 dark:bg-gray-900">
                            <button type="button" data-copy-from="complete-verify-token"
                                    class="copy-btn px-3 py-2 text-xs bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 transition">
                                Copiar
                            </button>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Pasos para completar la conexi&oacute;n:</p>
                        <ol class="text-xs text-gray-500 dark:text-gray-400 space-y-1 list-decimal list-inside" id="complete-instructions">
                        </ol>
                    </div>
                </div>
            </div>

            {{-- Widget Snippet (Webchat) --}}
            <div id="complete-widget" class="space-y-4" style="display:none">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5 space-y-4">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Instala el Widget</h4>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">C&oacute;digo para insertar en tu sitio web</label>
                        <div class="relative">
                            <pre class="bg-gray-100 dark:bg-gray-700 rounded-md p-3 text-xs overflow-x-auto"><code id="complete-snippet-code"></code></pre>
                            <button type="button" id="btn-copy-snippet"
                                    class="copy-btn absolute top-2 right-2 px-2 py-1 text-xs bg-white dark:bg-gray-600 text-gray-600 dark:text-gray-200 rounded border border-gray-200 dark:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                                Copiar
                            </button>
                        </div>
                    </div>
                    <div class="pt-3 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Instrucciones:</p>
                        <ol class="text-xs text-gray-500 dark:text-gray-400 space-y-1 list-decimal list-inside">
                            <li>Copia el c&oacute;digo de arriba.</li>
                            <li>P&eacute;galo antes de la etiqueta <code>&lt;/body&gt;</code> en tu sitio web.</li>
                            <li>El widget aparecer&aacute; autom&aacute;ticamente en la esquina inferior derecha.</li>
                        </ol>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-center gap-4 mt-8">
                <a href="{{ route('settings.channels') }}" class="px-5 py-2 text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Ir a Canales
                </a>
                <button type="button" id="btn-connect-another"
                        class="px-5 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    Conectar Otro Canal
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var currentStep = 1;
        var selectedType = null;
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        var canCreate = {{ $canCreate ? 'true' : 'false' }};
        var savedChannelData = null;

        var typeLabels = {
            whatsapp: 'WhatsApp Business',
            facebook: 'Facebook Messenger',
            instagram: 'Instagram DM',
            webchat: 'Webchat'
        };

        var typeDescriptions = {
            whatsapp: 'Ingresa las credenciales de tu WhatsApp Business API.',
            facebook: 'Ingresa las credenciales de tu app de Meta para Facebook Messenger.',
            instagram: 'Ingresa las credenciales de tu cuenta de Instagram Business.',
            webchat: 'Configura los ajustes de tu widget de chat.'
        };

        // ── Navigation ──────────────────────────────────

        function goToStep(step) {
            currentStep = step;
            document.querySelectorAll('.wizard-step').forEach(function(el) {
                el.style.display = parseInt(el.dataset.wizardStep) === step ? '' : 'none';
            });

            // Update step indicators
            document.querySelectorAll('.step-circle').forEach(function(el) {
                var s = parseInt(el.dataset.step);
                if (s < step) {
                    el.className = el.className.replace(/border-\S+/g, '').replace(/bg-\S+/g, '').replace(/text-\S+/g, '');
                    el.classList.add('border-green-500', 'bg-green-500', 'text-white');
                    el.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
                } else if (s === step) {
                    el.className = el.className.replace(/border-\S+/g, '').replace(/bg-\S+/g, '').replace(/text-\S+/g, '');
                    el.classList.add('border-indigo-500', 'bg-indigo-500', 'text-white');
                    el.textContent = s;
                } else {
                    el.className = el.className.replace(/border-\S+/g, '').replace(/bg-\S+/g, '').replace(/text-\S+/g, '');
                    el.classList.add('border-gray-300', 'dark:border-gray-600', 'text-gray-400', 'dark:text-gray-500');
                    el.textContent = s;
                }
            });

            document.querySelectorAll('.step-line').forEach(function(el) {
                var afterStep = parseInt(el.dataset.afterStep);
                if (afterStep < step) {
                    el.classList.remove('bg-gray-300', 'dark:bg-gray-600');
                    el.classList.add('bg-green-500');
                } else {
                    el.classList.remove('bg-green-500');
                    el.classList.add('bg-gray-300', 'dark:bg-gray-600');
                }
            });
        }

        // ── Step 1: Type Selection ──────────────────────

        document.querySelectorAll('.channel-card:not([disabled])').forEach(function(card) {
            card.addEventListener('click', function() {
                if (!canCreate) return;

                selectedType = card.dataset.channelType;

                document.querySelectorAll('.channel-card').forEach(function(c) {
                    c.classList.remove('border-indigo-500', 'dark:border-indigo-400', 'bg-indigo-50', 'dark:bg-indigo-900/20', 'ring-2', 'ring-indigo-200', 'dark:ring-indigo-800');
                    c.classList.add('border-gray-200', 'dark:border-gray-700');
                });

                card.classList.remove('border-gray-200', 'dark:border-gray-700');
                card.classList.add('border-indigo-500', 'dark:border-indigo-400', 'bg-indigo-50', 'dark:bg-indigo-900/20', 'ring-2', 'ring-indigo-200', 'dark:ring-indigo-800');

                // Move to step 2 after a brief delay
                setTimeout(function() {
                    showStep2();
                }, 300);
            });
        });

        function showStep2() {
            document.getElementById('step2-title').textContent = 'Configurar ' + typeLabels[selectedType];
            document.getElementById('step2-desc').textContent = typeDescriptions[selectedType];

            document.querySelectorAll('.type-fields').forEach(function(el) {
                el.style.display = el.dataset.typeFields === selectedType ? '' : 'none';
            });

            goToStep(2);
        }

        // ── Step 2: Back and Next ───────────────────────

        document.getElementById('btn-back-1').addEventListener('click', function() {
            goToStep(1);
        });

        document.getElementById('btn-next-2').addEventListener('click', function() {
            // Basic client-side validation
            var name = document.getElementById('field-name').value.trim();
            if (!name) {
                document.getElementById('field-name').focus();
                document.getElementById('field-name').classList.add('border-red-500');
                return;
            }
            document.getElementById('field-name').classList.remove('border-red-500');

            goToStep(3);
            runValidation();
        });

        // ── Step 3: Validation ──────────────────────────

        function collectFormData() {
            var data = {
                type: selectedType,
                name: document.getElementById('field-name').value.trim(),
                brand_id: document.getElementById('field-brand_id') ? document.getElementById('field-brand_id').value : '',
            };

            if (selectedType === 'whatsapp') {
                data.phone_number_id = document.getElementById('field-phone_number_id').value.trim();
                data.waba_id = document.getElementById('field-waba_id').value.trim();
                data.app_id = document.getElementById('field-app_id').value.trim();
                data.access_token = document.getElementById('field-access_token').value.trim();
                data.verify_token = document.getElementById('field-verify_token').value.trim();
                data.app_secret = document.getElementById('field-app_secret').value.trim();
                data.display_phone = document.getElementById('field-display_phone').value.trim();
            } else if (selectedType === 'facebook') {
                data.page_id = document.getElementById('field-fb-page_id').value.trim();
                data.page_access_token = document.getElementById('field-fb-page_access_token').value.trim();
                data.app_secret = document.getElementById('field-fb-app_secret').value.trim();
                data.verify_token = document.getElementById('field-fb-verify_token').value.trim();
            } else if (selectedType === 'instagram') {
                data.instagram_account_id = document.getElementById('field-ig-instagram_account_id').value.trim();
                data.page_id = document.getElementById('field-ig-page_id').value.trim();
                data.page_access_token = document.getElementById('field-ig-page_access_token').value.trim();
                data.app_secret = document.getElementById('field-ig-app_secret').value.trim();
                data.verify_token = document.getElementById('field-ig-verify_token').value.trim();
            } else if (selectedType === 'webchat') {
                data.allowed_origins = document.getElementById('field-wc-allowed_origins').value.trim();
            }

            return data;
        }

        function runValidation() {
            document.getElementById('validation-loading').style.display = '';
            document.getElementById('validation-success').style.display = 'none';
            document.getElementById('validation-error').style.display = 'none';
            document.getElementById('btn-save').style.display = 'none';

            var data = collectFormData();

            fetch('{{ route("settings.channels.wizard.validate") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                document.getElementById('validation-loading').style.display = 'none';

                if (result.success) {
                    document.getElementById('validation-success').style.display = '';
                    document.getElementById('btn-save').style.display = '';
                    var detail = '';
                    if (result.details) {
                        var parts = [];
                        for (var key in result.details) {
                            if (result.details[key]) parts.push(result.details[key]);
                        }
                        detail = parts.join(' — ');
                    }
                    document.getElementById('validation-success-detail').textContent = detail || result.message;
                } else {
                    document.getElementById('validation-error').style.display = '';
                    document.getElementById('validation-error-message').textContent = result.message || 'Error desconocido.';
                }
            })
            .catch(function(err) {
                document.getElementById('validation-loading').style.display = 'none';
                document.getElementById('validation-error').style.display = '';
                document.getElementById('validation-error-message').textContent = 'Error de red. Verifica tu conexi\u00f3n e intenta de nuevo.';
            });
        }

        // ── Step 3: Back and Save ───────────────────────

        document.getElementById('btn-back-2').addEventListener('click', function() {
            goToStep(2);
        });

        document.getElementById('btn-save').addEventListener('click', function() {
            var btn = document.getElementById('btn-save');
            btn.disabled = true;
            btn.textContent = 'Guardando...';

            var data = collectFormData();

            fetch('{{ route("settings.channels.wizard.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (result.success) {
                    savedChannelData = result;
                    showComplete(result);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Guardar Canal \u2192';
                    document.getElementById('validation-error').style.display = '';
                    document.getElementById('validation-error-message').textContent = result.message || 'Error al guardar.';
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = 'Guardar Canal \u2192';
                document.getElementById('validation-error').style.display = '';
                document.getElementById('validation-error-message').textContent = 'Error de red al guardar.';
            });
        });

        // ── Step 4: Complete ────────────────────────────

        function showComplete(result) {
            goToStep(4);

            document.getElementById('complete-title').textContent = '\u00a1' + result.channel.name + ' conectado!';
            document.getElementById('complete-subtitle').textContent = 'Tu canal de ' + typeLabels[result.channel.type] + ' est\u00e1 listo para recibir mensajes.';

            if (result.webhook_url) {
                document.getElementById('complete-webhook').style.display = '';
                document.getElementById('complete-widget').style.display = 'none';
                document.getElementById('complete-webhook-url').value = result.webhook_url;
                document.getElementById('complete-verify-token').value = result.channel.verify_token || '';

                var instructions = document.getElementById('complete-instructions');
                instructions.innerHTML = '';

                var steps = [];
                if (result.channel.type === 'whatsapp') {
                    steps = [
                        'Ve a <a href="https://developers.facebook.com/" target="_blank" class="text-indigo-500 hover:underline">developers.facebook.com</a> e inicia sesi\u00f3n.',
                        'Selecciona tu app y ve a <strong>WhatsApp &gt; Configuraci\u00f3n</strong>.',
                        'En la secci\u00f3n <strong>Webhook</strong>, haz clic en <strong>Editar</strong>.',
                        'Pega la <strong>URL del Webhook</strong> y el <strong>Verify Token</strong>.',
                        'Haz clic en <strong>Verificar y guardar</strong>.',
                        'Suscr\u00edbete al campo <strong>messages</strong>.'
                    ];
                } else if (result.channel.type === 'facebook') {
                    steps = [
                        'Ve a <a href="https://developers.facebook.com/" target="_blank" class="text-indigo-500 hover:underline">developers.facebook.com</a>.',
                        'Selecciona tu app y ve a <strong>Messenger &gt; Settings</strong>.',
                        'En <strong>Webhooks</strong>, haz clic en <strong>Add Callback URL</strong>.',
                        'Pega la <strong>URL del Webhook</strong> y el <strong>Verify Token</strong>.',
                        'Suscr\u00edbete a los eventos <strong>messages</strong> y <strong>messaging_postbacks</strong>.'
                    ];
                } else if (result.channel.type === 'instagram') {
                    steps = [
                        'Ve a <a href="https://developers.facebook.com/" target="_blank" class="text-indigo-500 hover:underline">developers.facebook.com</a>.',
                        'Selecciona tu app y ve a <strong>Instagram &gt; Settings</strong>.',
                        'Configura el <strong>Webhook</strong> con la URL y Verify Token.',
                        'Suscr\u00edbete al campo <strong>messages</strong>.'
                    ];
                }

                steps.forEach(function(text) {
                    var li = document.createElement('li');
                    li.innerHTML = text;
                    instructions.appendChild(li);
                });
            } else if (result.widget_snippet) {
                document.getElementById('complete-webhook').style.display = 'none';
                document.getElementById('complete-widget').style.display = '';
                document.getElementById('complete-snippet-code').textContent = result.widget_snippet;
            }
        }

        // ── Connect Another ─────────────────────────────

        document.getElementById('btn-connect-another').addEventListener('click', function() {
            // Reset form
            selectedType = null;
            savedChannelData = null;
            document.getElementById('field-name').value = '';
            if (document.getElementById('field-brand_id')) document.getElementById('field-brand_id').value = '';
            document.querySelectorAll('.type-fields input, .type-fields textarea').forEach(function(el) { el.value = ''; });
            document.querySelectorAll('.channel-card').forEach(function(c) {
                c.classList.remove('border-indigo-500', 'dark:border-indigo-400', 'bg-indigo-50', 'dark:bg-indigo-900/20', 'ring-2', 'ring-indigo-200', 'dark:ring-indigo-800');
                c.classList.add('border-gray-200', 'dark:border-gray-700');
            });
            goToStep(1);
        });

        // ── Copy Buttons ────────────────────────────────

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.copy-btn');
            if (!btn) return;

            var text = '';
            if (btn.dataset.copyFrom) {
                text = document.getElementById(btn.dataset.copyFrom).value;
            } else if (btn.id === 'btn-copy-snippet') {
                text = document.getElementById('complete-snippet-code').textContent;
            }

            if (text) {
                navigator.clipboard.writeText(text).then(function() {
                    var original = btn.textContent;
                    btn.textContent = '\u00a1Copiado!';
                    setTimeout(function() { btn.textContent = original; }, 2000);
                });
            }
        });
    });
    </script>
    @endpush
</x-app-layout>
