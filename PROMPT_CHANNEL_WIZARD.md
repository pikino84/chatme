# Prompt: Channel Connection Wizard for ChatMe

---

You are a senior Laravel SaaS architect.

I am building a multi-tenant CRM SaaS called **ChatMe** — a conversational CRM platform for WhatsApp, Instagram, Facebook, and Webchat.

---

## STACK

- Laravel 11.31 / PHP 8.4
- Livewire 3.6.4
- Tailwind CSS
- PostgreSQL 18
- Multi-tenant architecture: all tenant tables use `organization_id` column + global scope `OrganizationScope`
- Tenant resolution: `app('tenant')` returns the current `Organization` model
- Configuration stored as `encrypted:array` cast on the Channel model
- UUID auto-generated on Channel creation

---

## CRITICAL CONSTRAINT: NO ALPINE.JS

**Alpine.js does NOT work in our production environment.** Livewire 3 should inject it via `@livewireScripts` but it fails on production (app.chatme.com.mx).

**Rules:**
- Do NOT use Alpine.js directives: `x-data`, `x-show`, `x-model`, `x-cloak`, `x-on:`, `:class`, `@click`
- Use **Livewire 3 properties and wire: directives** for all interactivity (`wire:model`, `wire:click`, `wire:submit`)
- For anything Livewire can't handle, use vanilla JS with `@push('scripts')` and `document.addEventListener('DOMContentLoaded', ...)`
- Use `style="display:none"` for initially hidden elements, not `x-cloak`
- The app layout has `@stack('scripts')` available

---

## EXISTING CODE — Channel Model

```php
<?php
namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Channel extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'brand_id', 'uuid', 'type', 'name', 'configuration', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Channel $channel) {
            if (empty($channel->uuid)) {
                $channel->uuid = (string) Str::uuid();
            }
        });
    }

    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }
    public function form(): HasOne { return $this->hasOne(ChannelForm::class); }
    public function isWhatsApp(): bool { return $this->type === 'whatsapp'; }
    public function getWhatsAppConfig(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }
}
```

---

## EXISTING CODE — ChannelController (current form-based, NOT wizard)

The current channel management is at `/settings/channels` with a traditional form. The controller already handles:

- **store()**: validates per-type with switch statement, builds config, creates Channel
- **buildConfig()**: constructs configuration array per type (preserves secrets on update)
- **show()**: displays webhook URLs and widget snippets per type
- **Validation rules per type:**

```php
// WhatsApp
'phone_number_id' => 'required|string|max:255',
'waba_id' => 'required|string|max:255',
'access_token' => 'required|string|max:1000',
'verify_token' => 'required|string|max:255',
'app_secret' => 'required|string|max:255',
'display_phone' => 'required|string|max:50',

// Webchat
'allowed_origins' => 'nullable|string',

// Facebook
'page_id' => 'required|string|max:255',
'page_access_token' => 'required|string|max:1000',
'app_secret' => 'required|string|max:255',
'verify_token' => 'required|string|max:255',

// Instagram
'instagram_account_id' => 'required|string|max:255',
'page_id' => 'required|string|max:255',
'page_access_token' => 'required|string|max:1000',
'app_secret' => 'required|string|max:255',
'verify_token' => 'required|string|max:255',
```

---

## EXISTING CODE — WhatsAppService

```php
class WhatsAppService
{
    private const API_VERSION = 'v21.0';
    private const BASE_URL = 'https://graph.facebook.com';

    public function sendTextMessage(Channel $channel, string $to, string $body): ?array
    {
        $phoneNumberId = $channel->getWhatsAppConfig('phone_number_id');
        $accessToken = $channel->getWhatsAppConfig('access_token');
        if (!$phoneNumberId || !$accessToken) { return null; }

        $response = Http::withToken($accessToken)->timeout(30)->post(
            self::BASE_URL.'/'.self::API_VERSION.'/'.$phoneNumberId.'/messages',
            ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $to, 'type' => 'text', 'text' => ['body' => $body]]
        );
        return $response->successful() ? $response->json() : null;
    }
}
```

---

## EXISTING CONFIGURATION STRUCTURES

```php
// WhatsApp
[
    'phone_number_id' => '1234567890',
    'waba_id' => '9876543210',
    'access_token' => 'EAA...',
    'verify_token' => 'my-custom-verify-token',
    'app_secret' => 'abc123...',
    'display_phone' => '+52 1 555 123 4567',
]

// Webchat
[
    'allowed_origins' => ['https://example.com', 'https://shop.example.com'],
]

// Facebook Messenger
[
    'page_id' => '123456789',
    'page_access_token' => 'EAA...',
    'app_secret' => 'abc123...',
    'verify_token' => 'my-fb-verify-token',
]

// Instagram
[
    'instagram_account_id' => '17841400000',
    'page_id' => '123456789',
    'page_access_token' => 'EAA...',
    'app_secret' => 'abc123...',
    'verify_token' => 'my-ig-verify-token',
]
```

---

## EXISTING WEBHOOK URLS

```
WhatsApp:  /api/webhooks/whatsapp/{channelUuid}
Facebook:  /api/webhooks/facebook/{channelUuid}
Instagram: /api/webhooks/instagram/{channelUuid}
Webchat:   Widget snippet: <script src="/webchat/widget.js" data-channel="{uuid}" data-api="/api/webchat/{uuid}"></script>
```

---

## EXISTING ROUTES (channels management — settings section)

```
GET  /settings/channels                     → ChannelController@index
GET  /settings/channels/create              → ChannelController@create
POST /settings/channels                     → ChannelController@store
GET  /settings/channels/{channel}           → ChannelController@show
GET  /settings/channels/{channel}/edit      → ChannelController@edit
POST /settings/channels/{channel}/update    → ChannelController@update
POST /settings/channels/{channel}/toggle    → ChannelController@toggleActive
POST /settings/channels/{channel}/delete    → ChannelController@destroy
```

---

## EXISTING PERMISSIONS

- `channels.view` — view channel list and details
- `channels.manage` — create, edit, delete, toggle channels

---

## EXISTING BILLING / FEATURE GATING

- Feature: `max_channels` (limit) — Starter: 1, Professional: 5, Enterprise: unlimited
- Feature: `webchat_enabled` (boolean) — Starter: false, Professional: true, Enterprise: true
- Feature: `whatsapp_enabled` (boolean) — Starter: true, Professional: true, Enterprise: true
- Middleware: `feature:{code}` and `usage.limit:{code}`
- Service: `BillingService::checkFeature()`, `BillingService::checkLimit()`

---

## BRANDS (optional association)

Channels can optionally belong to a Brand. Brands are part of the multi-brand architecture:

```php
// Brand model fields: id, organization_id, name, slug, description, logo_url, color, is_active, settings (jsonb: ai_context)
// Channel has: brand_id (nullable FK)
// Conversations inherit brand_id from their channel
```

---

## WHAT I WANT TO BUILD

A **step-by-step onboarding wizard** that replaces the current flat form with a guided flow for connecting channels. This should be a **Livewire component** that lives alongside the existing ChannelController (not replace it — the existing CRUD stays for editing).

### Wizard Steps:

**Step 1 — Select Channel Type**
- Show 4 cards: WhatsApp, Instagram, Facebook, Webchat
- Each card shows: icon/emoji, name, brief description
- Check billing: if `webchat_enabled` is false for user's plan, show "upgrade" badge
- Check `max_channels` limit before proceeding
- Visual: highlighted card on selection

**Step 2 — Configuration Form**
- Dynamic form based on selected type
- Show contextual help text for each field (where to find the value in Meta dashboard, etc.)
- For WhatsApp: phone_number_id, waba_id, access_token, verify_token, app_secret, display_phone
- For Facebook: page_id, page_access_token, app_secret, verify_token
- For Instagram: instagram_account_id, page_id, page_access_token, app_secret, verify_token
- For Webchat: channel name, allowed_origins
- All channels: name field, optional brand selector
- Password fields for tokens/secrets
- Real-time validation via Livewire

**Step 3 — Validate & Test Connection**
- For WhatsApp: attempt a test API call to `graph.facebook.com/v21.0/{phone_number_id}` with the access_token to verify credentials are valid
- For Facebook: test call to `graph.facebook.com/v21.0/{page_id}?access_token={token}` to verify page exists
- For Instagram: test call to `graph.facebook.com/v21.0/{instagram_account_id}?access_token={token}`
- For Webchat: just validate the origins format (URLs)
- Show success/error result with details
- Allow going back to fix credentials if validation fails

**Step 4 — Save Channel**
- Create the Channel record via the same logic as ChannelController@store
- Use `app('tenant')->id` for organization_id
- UUID auto-generated by model
- Configuration stored as encrypted array
- Channel created as `is_active = true`

**Step 5 — Confirmation & Next Steps**
- Show success message with channel details
- For webhook-based channels (WhatsApp, Facebook, Instagram):
  - Display webhook URL with copy button
  - Display verify_token with copy button
  - Show step-by-step instructions for configuring the webhook in Meta Developer Console
- For Webchat:
  - Display the widget snippet code with copy button
  - Show embed instructions
- "Go to Channels" button
- "Connect Another Channel" button

### Technical Requirements:

1. **Multi-tenant isolation**: use `app('tenant')->id`, respect OrganizationScope
2. **Permission check**: require `channels.manage` permission
3. **Billing check**: verify `max_channels` limit and type-specific features before allowing creation
4. **No Alpine.js**: use only `wire:` directives for Livewire interactivity
5. **Encrypted config**: stored via Channel model's `encrypted:array` cast
6. **Brand support**: optional brand_id selector if brands exist
7. **Credential validation**: test API calls in Step 3 before saving
8. **Copy to clipboard**: use vanilla JS for clipboard operations
9. **Dark mode**: all UI must support Tailwind dark mode classes (`dark:bg-gray-800`, etc.)
10. **Spanish UI**: all labels and messages in Spanish (the app UI is in Spanish)

---

## DELIVERABLES

1. **Livewire Component**: `app/Http/Livewire/ChannelWizard.php`
   - Properties: $step, $type, $name, $brandId, all config fields per type
   - Methods: selectType(), nextStep(), previousStep(), validateCredentials(), saveChannel()
   - Computed: currentStepTitle, webhookUrl, widgetSnippet
   - Lifecycle: mount() checks permissions and billing

2. **Blade View**: `resources/views/livewire/channel-wizard.blade.php`
   - Step indicator (numbered circles with connecting lines)
   - Responsive, clean Tailwind UI
   - Dark mode support throughout
   - Spanish labels

3. **Route**: Add to web.php tenant routes
   ```
   GET /settings/channels/wizard → renders the Livewire wizard page
   ```

4. **Credential Validation Service** (optional — or inline in component):
   - `validateWhatsAppCredentials(config): bool|string`
   - `validateFacebookCredentials(config): bool|string`
   - `validateInstagramCredentials(config): bool|string`
   - `validateWebchatConfig(config): bool|string`

5. **Navigation**: Add "Conectar Canal" button on `/settings/channels` index that links to the wizard

---

## UI FLOW DIAGRAM

```
┌─────────────────────────────────────────────┐
│           Paso 1: Seleccionar Tipo          │
│                                             │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐       │
│  │WhatsApp │ │Facebook │ │Instagram│       │
│  │  ✓ ✓    │ │         │ │         │       │
│  └─────────┘ └─────────┘ └─────────┘       │
│  ┌─────────┐                                │
│  │ Webchat │                                │
│  └─────────┘                                │
│                                             │
│  [Billing limit warning if applicable]      │
│                         [Siguiente →]       │
└─────────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────┐
│         Paso 2: Configuración               │
│                                             │
│  Nombre del Canal: [__________________]     │
│  Marca (opcional): [▼ Sin marca       ]     │
│                                             │
│  ┌─ Campos según tipo ──────────────────┐   │
│  │ Phone Number ID: [________________] │   │
│  │ WABA ID:         [________________] │   │
│  │ Access Token:    [________________] │   │
│  │ Verify Token:    [________________] │   │
│  │ App Secret:      [________________] │   │
│  │ Display Phone:   [________________] │   │
│  │                                     │   │
│  │ ℹ️ Help text: "Find this in Meta    │   │
│  │   Developer Console > WhatsApp >    │   │
│  │   API Setup"                        │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  [← Atrás]              [Siguiente →]       │
└─────────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────┐
│      Paso 3: Verificar Conexión             │
│                                             │
│  Verificando credenciales...                │
│  ┌─────────────────────────────────────┐    │
│  │  ✅ Conexión exitosa                │    │
│  │  Phone Number ID verificado         │    │
│  │  Nombre del negocio: "Mi Empresa"   │    │
│  └─────────────────────────────────────┘    │
│                                             │
│  OR                                         │
│                                             │
│  ┌─────────────────────────────────────┐    │
│  │  ❌ Error de conexión               │    │
│  │  "Invalid OAuth access token"       │    │
│  │  Revisa tu Access Token             │    │
│  └─────────────────────────────────────┘    │
│                                             │
│  [← Corregir]           [Guardar Canal →]   │
└─────────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────┐
│          Paso 4: Guardando...               │
│                                             │
│  [Spinner] Creando canal...                 │
│  (This step is automatic/transitional)      │
└─────────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────┐
│      Paso 5: ¡Canal Conectado!              │
│                                             │
│  ✅ Tu canal "WhatsApp Ventas" está listo   │
│                                             │
│  ┌─ Configura en Meta ──────────────────┐   │
│  │                                      │   │
│  │ URL del Webhook:                     │   │
│  │ [https://app.chatme.../wa/uuid] [📋]│   │
│  │                                      │   │
│  │ Verify Token:                        │   │
│  │ [my-verify-token]              [📋]  │   │
│  │                                      │   │
│  │ Instrucciones:                       │   │
│  │ 1. Ve a developers.facebook.com      │   │
│  │ 2. Selecciona tu app                 │   │
│  │ 3. Ve a WhatsApp > Configuration     │   │
│  │ 4. En Webhook, pega la URL           │   │
│  │ 5. Ingresa el Verify Token           │   │
│  │ 6. Suscríbete a: messages            │   │
│  └──────────────────────────────────────┘   │
│                                             │
│  [Ir a Canales]   [Conectar Otro Canal]     │
└─────────────────────────────────────────────┘
```

---

## IMPORTANT NOTES

- The existing ChannelController CRUD stays intact — the wizard is an **additional** entry point for creating channels
- The wizard should use the **same validation rules and configuration structure** as the existing controller
- After the wizard creates a channel, redirect or link to the existing `settings.channels.show` route for ongoing management
- All strings in the UI must be in **Spanish**
- Use `wire:model.live` for real-time validation, `wire:model` for normal binding
- Credential test calls should use `Http::timeout(10)` to avoid long waits
- Handle the case where the user's plan doesn't support the selected channel type gracefully (show upgrade message, not an error)

---

## EXAMPLE LIVEWIRE PATTERN (for reference)

Since Alpine.js doesn't work, here's how interactivity should be handled:

```blade
{{-- Livewire wire: directives for state management --}}
<div wire:click="selectType('whatsapp')"
     class="cursor-pointer border rounded-lg p-4 {{ $type === 'whatsapp' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-300 dark:border-gray-600' }}">
    WhatsApp
</div>

{{-- Conditional rendering via Livewire --}}
@if($step === 2)
    <div>Step 2 content</div>
@endif

{{-- Form submission via Livewire --}}
<form wire:submit="saveChannel">
    <input wire:model="name" type="text">
    @error('name') <span class="text-red-500">{{ $message }}</span> @enderror
</form>

{{-- Loading states --}}
<button wire:click="validateCredentials" wire:loading.attr="disabled">
    <span wire:loading.remove wire:target="validateCredentials">Verificar</span>
    <span wire:loading wire:target="validateCredentials">Verificando...</span>
</button>
```

```blade
{{-- Clipboard copy with vanilla JS (no Alpine) --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-copy]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var text = btn.dataset.copy;
            navigator.clipboard.writeText(text).then(function() {
                var original = btn.textContent;
                btn.textContent = 'Copiado!';
                setTimeout(function() { btn.textContent = original; }, 2000);
            });
        });
    });
});
</script>
@endpush
```

---

Return full, production-ready code for all files. Follow Laravel and Livewire 3 best practices.
