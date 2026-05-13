# ChatMe SaaS - Contexto Completo del Proyecto

---

## 1. DESCRIPCIÓN GENERAL

ChatMe es una plataforma SaaS de CRM conversacional omnicanal construida en Laravel.

Permite gestionar conversaciones provenientes de:
- WhatsApp Business API
- Instagram Graph API
- Facebook Messenger API
- Webchat (widget embebible)

Cada conversación puede convertirse en un lead/deal dentro de un CRM con pipelines tipo Kanban.

### Funcionalidades principales:
- Mensajería omnicanal (inbox unificado)
- CRM con deals, pipelines y etapas
- Directorio de contactos unificado
- Campañas de mensajería (broadcast masivo + secuencias drip)
- Automatizaciones (asignación automática, auto-respuestas, alertas de deals estancados)
- Base de conocimiento (Knowledge Base)
- Respuestas asistidas por IA (RAG con pgvector)
- Analytics y reportes con Chart.js
- Billing SaaS con feature gating por plan
- Multi-marca (brands) para organizaciones multi-concepto
- Admin backoffice para gestión del SaaS

### Modelo de negocio:
- Multi-tenant SaaS con 3 planes: Starter ($499 MXN/mo), Professional ($999 MXN/mo), Enterprise ($2,499 MXN/mo)
- Cada feature se activa/desactiva por plan via feature gating
- Revenue: suscripciones + cargos por uso en features medidas

### Mercado objetivo:
- PyMEs en México que usan WhatsApp como canal principal de atención al cliente
- Equipos de ventas que necesitan CRM integrado con mensajería
- Equipos de soporte gestionando conversaciones multicanal

---

## 2. STACK TECNOLÓGICO

| Componente | Tecnología |
|---|---|
| Backend | Laravel 11.31, PHP 8.4 |
| Base de datos | PostgreSQL 18 + pgvector |
| Cache/Queues | Redis + Laravel Horizon (3 colas: critical/default/low) |
| Frontend | Blade + Livewire 3.x + Tailwind CSS |
| Auth | Jetstream 5.4 + Fortify (2FA habilitado) |
| Permisos | Spatie Permission 6.24 |
| Realtime | Laravel Reverb (WebSockets) |
| AI | OpenAI API (embeddings + chat completions) |
| Payments | Stripe (HTTP directo, sin Cashier) |
| Jobs | Horizon con queues: critical, default, low |

### Dependencias (composer.json):
```json
{
  "php": "^8.2",
  "laravel/framework": "^11.31",
  "laravel/horizon": "^5.45",
  "laravel/jetstream": "^5.4",
  "laravel/reverb": "^1.0",
  "laravel/sanctum": "^4.0",
  "laravel/tinker": "^2.9",
  "livewire/livewire": "^3.6.4",
  "spatie/laravel-permission": "^6.24"
}
```

---

## 3. ARQUITECTURA

Arquitectura SaaS **modular monolítica** (no microservicios).

### Dominios:
| Dominio | Uso |
|---|---|
| `chatme.com.mx` | Landing page pública |
| `app.chatme.com.mx` | Aplicación principal (tenant) |
| `admin.chatme.com.mx` | Admin backoffice SaaS |

### Resolución de tenant:
- Por subdominio slug: `empresa1.chatme.com.mx` → middleware `ResolveTenant`
- Por usuario autenticado: `app.chatme.com.mx` → middleware `ResolveUserTenant`

### Modelo multi-tenant:
- **Aislamiento por columna `organization_id`** en todas las tablas tenant
- Global scope: `OrganizationScope` (aplica WHERE organization_id automáticamente)
- Trait: `BelongsToOrganization` (auto-asigna organization_id en boot)
- Policies: extienden `TenantPolicy` base
- Container binding: `app('tenant')` para obtener la organización actual
- Cross-tenant queries: solo con `withoutGlobalScopes()` explícito en cada query y subquery

### Estructura de código:
```
app/
├── Actions/Fortify/          # Acciones de autenticación
├── Console/Commands/          # Comandos artisan (DeployApp, MonitorPerformance)
├── Events/                    # Eventos (Conversation*, Message*, TenantBroadcast)
├── Exceptions/                # BillingLimitException
├── Http/
│   ├── Controllers/
│   │   ├── SaaSAdmin/        # Controllers del admin backoffice
│   │   ├── Tenant/           # Controllers de la app tenant
│   │   ├── Webchat/          # Controller del widget webchat
│   │   └── Webhooks/         # WhatsApp + Stripe webhooks
│   ├── Middleware/            # CheckFeature, CheckUsageLimit, EnsureActiveSubscription,
│   │                         # ResolveTenant, ResolveUserTenant, SecurityHeaders, etc.
│   └── Responses/            # LoginResponse personalizado
├── Jobs/                      # SendWhatsAppMessage, SendCampaignMessage, ProcessDripStep, etc.
├── Listeners/                 # AuditLoginListener, AutomationListener
├── Models/                    # 35+ modelos Eloquent
│   ├── Scopes/               # OrganizationScope
│   └── Traits/               # BelongsToOrganization
├── Policies/                  # 18+ policies (todas extienden TenantPolicy)
├── Providers/                 # App, Fortify, Horizon, Jetstream
├── Services/                  # 15+ servicios de lógica de negocio
└── View/Components/           # AppLayout, GuestLayout
```

### Patrones clave:
- **Controllers**: solo coordinan requests, delegan a Services
- **Services**: contienen toda la lógica de negocio
- **Policies**: controlan autorización (tenant-aware)
- **Models**: solo relaciones, atributos, casts
- **Feature gating**: `feature:{code}` middleware + `BillingService::checkFeature()`
- **Usage limits**: `usage.limit:{code}` middleware + `BillingService::checkLimit()/incrementUsage()`
- **Integraciones externas**: graceful degradation sin credenciales

---

## 4. ESQUEMA DE BASE DE DATOS

Convención: todas las tablas tenant incluyen `organization_id`, `created_at`, `updated_at`.
Tablas globales (plans, plan_features, cache, jobs) NO tienen organization_id.

### Core Tables

**organizations**
- id, name, slug (unique), status (enum: active/suspended/trial), settings (jsonb), timestamps
- settings jsonb: maintenance_mode, ai_enabled, ai_model, ai_temperature, logo, timezone

**branches**
- id, organization_id (FK cascade), brand_id (FK nullable), name, address, phone, is_active, timestamps

**users**
- id, organization_id (nullable FK nullOnDelete), name, email (unique), email_verified_at, password
- two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at
- remember_token, current_team_id, profile_photo_path, is_active (boolean default true), timestamps

**password_reset_tokens**
- email (PK), token, created_at

**sessions**
- id (PK string), user_id (FK), ip_address, user_agent, payload, last_activity

### Brands

**brands**
- id, organization_id (FK cascade), name, slug, description, logo_url, color, is_active, settings (jsonb: ai_context), timestamps

### Conversations Tables

**channels**
- id, uuid (unique), organization_id (FK cascade), brand_id (FK nullable), type (enum: whatsapp/webchat/email/facebook/instagram), name, configuration (text), is_active, timestamps
- configuration: almacena verify_token, app_secret, phone_number_id, access_token según el tipo

**conversations**
- id, organization_id (FK cascade), channel_id (FK restrict), assigned_user_id (nullable FK nullOnDelete), branch_id (nullable FK nullOnDelete), brand_id (FK nullable), contact_id (FK nullable)
- status (enum: open/pending/closed), subject, contact_name, contact_identifier
- priority (enum: low/normal/high/urgent), metadata (jsonb), closed_at, last_message_at, timestamps

**messages**
- id, organization_id (FK cascade), conversation_id (FK cascade), user_id (nullable FK nullOnDelete)
- type (enum: text/image/audio/video/document/template), body (text), external_id, metadata (jsonb)
- direction (enum: inbound/outbound), timestamps

**conversation_assignments**
- id, organization_id (FK cascade), conversation_id (FK cascade), user_id (FK cascade)
- assigned_by (nullable FK nullOnDelete), timestamps

**conversation_transfers**
- id, organization_id (FK cascade), conversation_id (FK cascade)
- from_user_id (FK cascade), to_user_id (FK cascade), reason (text), timestamps

**conversation_sla_logs**
- id, organization_id (FK cascade), conversation_id (FK cascade)
- metric (string), target_seconds (int), actual_seconds (int nullable), breached (boolean default false), timestamps

**channel_forms**
- id, organization_id (FK cascade), channel_id (FK restrict), template_key (string), is_active, timestamps

### CRM Tables

**pipelines**
- id, organization_id (FK cascade), name, is_default (boolean), timestamps

**pipeline_stages**
- id, organization_id (FK cascade), pipeline_id (FK cascade), name, position (int), is_won (boolean), is_lost (boolean), max_duration_hours (int nullable), timestamps

**tags**
- id, organization_id (FK cascade), name, timestamps
- unique constraint: [organization_id, name]

**deals**
- id, organization_id (FK cascade), pipeline_id (FK restrict), pipeline_stage_id (FK restrict)
- conversation_id (nullable FK nullOnDelete), assigned_user_id (nullable FK nullOnDelete), contact_id (FK nullable)
- contact_name, contact_email, contact_phone
- value (decimal 12,2), currency (string 3 default MXN), stage_entered_at
- status (enum: open/won/lost), expected_close_date, closed_at, timestamps

**deal_tag** (pivot)
- deal_id (FK cascade), tag_id (FK cascade)

**deal_stage_history**
- id, organization_id (FK cascade), deal_id (FK cascade)
- from_stage_id (nullable FK nullOnDelete), to_stage_id (FK restrict)
- changed_by (nullable FK nullOnDelete), changed_at (timestamp), timestamps

**deal_notes**
- id, organization_id (FK cascade), deal_id (FK cascade), user_id (FK cascade), body (text), timestamps

**deal_attachments**
- id, organization_id (FK cascade), deal_id (FK cascade), user_id (FK cascade)
- file_name, file_path, file_size (int), mime_type, timestamps

**deal_commissions**
- id, organization_id (FK cascade), deal_id (FK cascade), user_id (FK cascade)
- amount (decimal 10,2), percentage (decimal 5,2 nullable), status (enum: pending/paid/canceled), paid_at (nullable), timestamps

### Contacts

**contacts**
- id, organization_id (FK cascade), name, email (nullable), phone (nullable)
- external_id (nullable, para WhatsApp/FB/IG ID), channel_type (nullable)
- company (nullable), notes (text nullable), metadata (jsonb nullable)
- timestamps
- unique constraint: [organization_id, phone] y [organization_id, email] (nullable unique)

### AI / Knowledge Base Tables

**kb_categories**
- id, organization_id (FK cascade), brand_id (FK nullable), name, description (text nullable), position (int), parent_id (self-referencing nullable FK), is_active, timestamps

**kb_articles**
- id, organization_id (FK cascade), brand_id (FK nullable), kb_category_id (FK restrict), created_by (FK), updated_by (FK)
- title, slug, content (text), status (enum: draft/published/archived), priority (int)
- visible_on_webchat, visible_on_whatsapp, visible_on_instagram, visible_on_facebook (booleans)
- published_at (nullable), embedding (vector 1536, pgvector, nullable), timestamps

**kb_versions**
- id, organization_id (FK cascade), kb_article_id (FK cascade), version_number (int)
- title, content (text), changed_by (FK), change_summary (text nullable), timestamps

### Billing Tables (globales — sin organization_id)

**plans**
- id, name, slug (unique), description (text), price_monthly (unsigned int), price_yearly (unsigned int)
- is_active (boolean), sort_order (smallint), trial_days (smallint), timestamps

**plan_features**
- id, code (unique), description, type (enum: boolean/limit), timestamps

**plan_feature_values**
- id, plan_id (FK cascade), plan_feature_id (FK cascade), value (string), timestamps

**organization_subscriptions**
- id, organization_id (FK cascade), plan_id (FK restrict)
- status (enum: active/trialing/canceled/expired), billing_cycle (enum: monthly/yearly)
- starts_at, ends_at, trial_ends_at (nullable), canceled_at (nullable), grace_period_ends_at (nullable), timestamps

**organization_usage_monthly**
- id, organization_id (FK cascade), feature_code (string), period (string YYYY-MM), usage (int), timestamps

### Campaigns Tables

**campaigns**
- id, organization_id (FK cascade), channel_id (FK restrict), name, type (enum: broadcast/drip)
- status (enum: draft/scheduled/running/completed/canceled)
- message_template (text), scheduled_at (nullable), completed_at (nullable)
- total_recipients (int default 0), sent_count (int default 0), failed_count (int default 0)
- timestamps

**campaign_recipients**
- id, campaign_id (FK cascade), contact_id (FK cascade)
- status (enum: pending/sent/delivered/failed), sent_at (nullable), error_message (nullable)
- timestamps

**drip_sequences**
- id, organization_id (FK cascade), name, status (enum: active/paused/archived)
- trigger_event (string), timestamps

**drip_sequence_steps**
- id, drip_sequence_id (FK cascade), position (int), delay_minutes (int)
- message_template (text), channel_type (enum: whatsapp/webchat/facebook/instagram)
- timestamps

**drip_enrollments**
- id, drip_sequence_id (FK cascade), contact_id (FK cascade)
- current_step (int default 0), status (enum: active/completed/canceled)
- next_step_at (nullable), timestamps

### Automations Tables

**automation_rules** (nombre real en código, "automations" en diseño)
- id, organization_id (FK cascade), name, type (enum: auto_assign/auto_response/schedule_response/stale_deal_alert)
- is_active (boolean default false), configuration (jsonb), schedule (jsonb nullable)
- timestamps

**automation_logs**
- id, automation_id (FK cascade), organization_id (FK cascade)
- trigger_type (string), trigger_id (nullable bigint), result (enum: success/failure/skipped)
- details (jsonb nullable), timestamps

### Admin Tables

**saas_alerts**
- id, organization_id (nullable FK), type (string), message (text), severity (enum), resolved (boolean), created_by (nullable FK), timestamps

### Audit Tables

**audit_logs**
- id, organization_id (nullable FK), user_id (nullable FK), action (string), auditable_type, auditable_id
- old_values (jsonb nullable), new_values (jsonb nullable), ip_address, user_agent, timestamps

### Infrastructure Tables (Laravel framework)
- cache, cache_locks (standard Laravel cache)
- jobs, job_batches, failed_jobs (standard Laravel queue)
- personal_access_tokens (Laravel Sanctum)

---

## 5. ROLES Y PERMISOS

### Roles (Spatie Permission):
| Rol | Descripción |
|---|---|
| `saas_admin` | Administrador del SaaS (accede a admin.chatme.com.mx) |
| `org_admin` | Administrador de organización (acceso total dentro del tenant) |
| `supervisor` | Supervisor (gestiona equipo, ve reportes) |
| `agent` | Agente (opera conversaciones, deals) |

### Permisos por módulo:

**Conversaciones**: conversations.view, conversations.create, conversations.update, conversations.delete, conversations.assign, conversations.transfer, conversations.close
**Canales**: channels.view, channels.create, channels.update, channels.delete
**CRM**: deals.view, deals.create, deals.update, deals.delete, deals.assign, pipelines.view, pipelines.create, pipelines.update, pipelines.delete, deal_notes.create, deal_notes.delete
**Knowledge Base**: kb.view, kb.create, kb.update, kb.delete, kb.publish
**Contactos**: contacts.view, contacts.create, contacts.update, contacts.delete, contacts.import
**Campañas**: campaigns.view, campaigns.create, campaigns.send, campaigns.delete
**Drip**: drip.view, drip.create, drip.update, drip.delete
**Automatizaciones**: automations.view, automations.create, automations.update, automations.delete
**Marcas**: brands.view, brands.create, brands.update, brands.delete

---

## 6. FEATURE GATING POR PLAN

### Catálogo de features:

| Feature Code | Tipo | Starter | Professional | Enterprise |
|---|---|---|---|---|
| max_agents | limit | 3 | 10 | unlimited |
| max_channels | limit | 1 | 5 | unlimited |
| max_conversations_monthly | limit | 100 | 1,000 | unlimited |
| max_messages_monthly | limit | 500 | 10,000 | unlimited |
| webchat_enabled | boolean | false | true | true |
| whatsapp_enabled | boolean | true | true | true |
| sla_tracking | boolean | false | true | true |
| api_access | boolean | false | false | true |
| custom_branding | boolean | false | false | true |
| kb_articles_limit | limit | 20 | 200 | unlimited |
| ai_suggestions_enabled | boolean | false | true | true |
| ai_queries_monthly | limit | 0 | 500 | unlimited |
| reports_enabled | boolean | false | true | true |
| campaigns_enabled | boolean | false | true | true |
| max_campaigns_monthly | limit | 0 | 10 | unlimited |
| automations_enabled | boolean | false | true | true |
| max_automations | limit | 0 | 10 | unlimited |
| max_contacts | limit | 100 | 5,000 | unlimited |
| drip_sequences_enabled | boolean | false | true | true |
| max_brands | limit | 1 | 5 | unlimited |

### Mecanismo:
- `BillingService::checkFeature($code)` — verifica features boolean
- `BillingService::checkLimit($code)` — verifica límites
- `BillingService::incrementUsage($code)` — incrementa contador mensual
- Middleware `feature:{code}` — bloquea ruta si feature deshabilitada
- Middleware `usage.limit:{code}` — bloquea ruta si límite alcanzado
- Middleware `subscription` — requiere suscripción activa

---

## 7. RUTAS COMPLETAS

### Landing (chatme.test / chatme.com.mx)
```
GET  /                          → landing
GET  /legal/terms               → legal.terms
GET  /legal/privacy             → legal.privacy
GET  /legal/data-deletion       → legal.data-deletion
```

### App Tenant (app.chatme.test / app.chatme.com.mx)

**Auth (Fortify/Jetstream)**
```
GET  /login                     → login
POST /login                     → login.store
POST /logout                    → logout
GET  /register                  → register
POST /register                  → register.store
GET  /forgot-password           → password.request
POST /forgot-password           → password.email
GET  /reset-password/{token}    → password.reset
POST /reset-password            → password.update
GET  /two-factor-challenge      → two-factor.login
POST /two-factor-challenge      → two-factor.login.store
GET  /user/confirm-password     → password.confirm
POST /user/confirm-password     → password.confirm.store
PUT  /user/password             → user-password.update
PUT  /user/profile-information  → user-profile-information.update
POST /user/two-factor-authentication      → two-factor.enable
DELETE /user/two-factor-authentication    → two-factor.disable
GET  /user/two-factor-qr-code            → two-factor.qr-code
GET  /user/two-factor-recovery-codes     → two-factor.recovery-codes
POST /user/two-factor-recovery-codes     → two-factor.regenerate-recovery-codes
```

**Dashboard**
```
GET  /dashboard                 → dashboard
GET  /tenant/dashboard          → tenant.dashboard
```

**Inbox (Conversaciones)**
```
GET  /inbox                                              → inbox
GET  /inbox/conversations/{conversation}                 → inbox.conversations.show
POST /inbox/conversations/{conversation}/assign          → inbox.conversations.assign
POST /inbox/conversations/{conversation}/close           → inbox.conversations.close
POST /inbox/conversations/{conversation}/messages        → inbox.conversations.messages.store
POST /inbox/conversations/{conversation}/read            → inbox.conversations.read
POST /inbox/conversations/{conversation}/reopen          → inbox.conversations.reopen
POST /inbox/conversations/{conversation}/transfer        → inbox.conversations.transfer
```

**CRM (Deals/Kanban)**
```
GET  /deals                     → deals.board
POST /deals                     → deals.store
GET  /deals/{deal}              → deals.show
POST /deals/{deal}/assign       → deals.assign
POST /deals/{deal}/move         → deals.move
POST /deals/{deal}/notes        → deals.notes.store
```

**Contacts**
```
GET    /contacts                → contacts.index
POST   /contacts                → contacts.store
GET    /contacts/create         → contacts.create
GET    /contacts/import         → contacts.import.form
POST   /contacts/import         → contacts.import
GET    /contacts/{contact}      → contacts.show
PUT    /contacts/{contact}      → contacts.update
DELETE /contacts/{contact}      → contacts.destroy
GET    /contacts/{contact}/edit → contacts.edit
```

**Campaigns**
```
GET    /campaigns                                          → campaigns.index
POST   /campaigns                                          → campaigns.store
GET    /campaigns/create                                   → campaigns.create
GET    /campaigns/{campaign}                               → campaigns.show
PUT    /campaigns/{campaign}                               → campaigns.update
DELETE /campaigns/{campaign}                               → campaigns.destroy
POST   /campaigns/{campaign}/cancel                        → campaigns.cancel
GET    /campaigns/{campaign}/edit                          → campaigns.edit
GET    /campaigns/{campaign}/recipients/select             → campaigns.recipients.select
POST   /campaigns/{campaign}/recipients                    → campaigns.recipients.add
DELETE /campaigns/{campaign}/recipients/{contactId}        → campaigns.recipients.remove
POST   /campaigns/{campaign}/send                          → campaigns.send
```

**Drip Sequences**
```
GET    /drip                                              → drip.index
POST   /drip                                              → drip.store
GET    /drip/create                                       → drip.create
GET    /drip/{sequence}                                   → drip.show
PUT    /drip/{sequence}                                   → drip.update
DELETE /drip/{sequence}                                   → drip.destroy
POST   /drip/{sequence}/activate                          → drip.activate
GET    /drip/{sequence}/edit                              → drip.edit
POST   /drip/{sequence}/enroll                            → drip.enroll
POST   /drip/{sequence}/enrollments/{enrollment}/cancel   → drip.enrollments.cancel
POST   /drip/{sequence}/pause                             → drip.pause
POST   /drip/{sequence}/steps                             → drip.steps.add
PUT    /drip/{sequence}/steps/{step}                      → drip.steps.update
DELETE /drip/{sequence}/steps/{step}                      → drip.steps.remove
```

**Automations**
```
GET    /automations                      → automations.index
POST   /automations                      → automations.store
GET    /automations/create               → automations.create
PUT    /automations/{rule}               → automations.update
DELETE /automations/{rule}               → automations.destroy
GET    /automations/{rule}/edit          → automations.edit
POST   /automations/{rule}/toggle        → automations.toggle
```

**Brands**
```
GET    /brands                  → brands.index
POST   /brands                  → brands.store
GET    /brands/create           → brands.create
GET    /brands/{brand}          → brands.show
PUT    /brands/{brand}          → brands.update
DELETE /brands/{brand}          → brands.destroy
GET    /brands/{brand}/edit     → brands.edit
```

**Knowledge Base**
```
GET  /kb/categories                         → kb.categories
POST /kb/categories                         → kb.categories.store
POST /kb/categories/{category}/update       → kb.categories.update
POST /kb/categories/{category}/delete       → kb.categories.destroy
GET  /kb/articles                           → kb.articles
POST /kb/articles                           → kb.articles.store
GET  /kb/articles/create                    → kb.articles.create
GET  /kb/articles/{article}                 → kb.articles.show
POST /kb/articles/{article}/update          → kb.articles.update
GET  /kb/articles/{article}/edit            → kb.articles.edit
POST /kb/articles/{article}/publish         → kb.articles.publish
POST /kb/articles/{article}/archive         → kb.articles.archive
POST /kb/articles/{article}/delete          → kb.articles.destroy
```

**Analytics**
```
GET  /analytics                 → analytics.index
GET  /analytics/export          → analytics.export
```

**Billing**
```
GET  /billing                   → billing.index
GET  /billing/plans             → billing.plans
POST /billing/change-plan       → billing.change-plan
POST /billing/cancel            → billing.cancel
POST /billing/checkout          → billing.checkout
GET  /billing/checkout/success  → billing.checkout.success
POST /billing/portal            → billing.portal
```

**Settings**
```
GET  /settings                              → settings.show
POST /settings                              → settings.update
GET  /settings/ai                           → settings.ai
POST /settings/ai                           → settings.ai.update
GET  /settings/channels                     → settings.channels
POST /settings/channels                     → settings.channels.store
GET  /settings/channels/create              → settings.channels.create
GET  /settings/channels/{channel}           → settings.channels.show
POST /settings/channels/{channel}/update    → settings.channels.update
GET  /settings/channels/{channel}/edit      → settings.channels.edit
POST /settings/channels/{channel}/toggle    → settings.channels.toggle
POST /settings/channels/{channel}/delete    → settings.channels.delete
GET  /settings/team                         → settings.team
POST /settings/team/{user}/role             → settings.team.role
POST /settings/team/{user}/toggle           → settings.team.toggle
```

**Health Checks**
```
GET  /health/app    → HealthCheckController@app
GET  /health/db     → HealthCheckController@db
GET  /health/redis  → HealthCheckController@redis
GET  /health/queue  → HealthCheckController@queue
```

### API Pública
```
POST api/webchat/{channelUuid}/session               → WebchatController@createSession
POST api/webchat/{channelUuid}/messages               → WebchatController@sendMessage
GET  api/webchat/{channelUuid}/messages               → WebchatController@getMessages
GET  api/webchat/{channelUuid}/form-schema            → WebchatController@formSchema
POST api/webchat/{channelUuid}/broadcasting/auth      → WebchatController@broadcastAuth
GET  api/webhooks/whatsapp/{channelUuid}              → WhatsAppWebhookController@verify
POST api/webhooks/whatsapp/{channelUuid}              → WhatsAppWebhookController@handle
POST api/webhooks/stripe                              → StripeWebhookController@handle
```

### Admin Backoffice (admin.chatme.test / admin.chatme.com.mx)
```
GET  /panel                                                 → saas-admin.dashboard

# Organizations
GET    /panel/organizations                                 → saas-admin.organizations.index
POST   /panel/organizations                                 → saas-admin.organizations.store
GET    /panel/organizations/create                          → saas-admin.organizations.create
GET    /panel/organizations/{organization}                  → saas-admin.organizations.show
PUT    /panel/organizations/{organization}                  → saas-admin.organizations.update
DELETE /panel/organizations/{organization}                  → saas-admin.organizations.destroy
GET    /panel/organizations/{organization}/edit             → saas-admin.organizations.edit
POST   /panel/organizations/{organization}/suspend          → saas-admin.organizations.suspend
POST   /panel/organizations/{organization}/activate         → saas-admin.organizations.activate

# Subscriptions
GET    /panel/subscriptions                                 → saas-admin.subscriptions.index
POST   /panel/subscriptions                                 → saas-admin.subscriptions.store
GET    /panel/subscriptions/create                          → saas-admin.subscriptions.create
GET    /panel/subscriptions/{subscription}                  → saas-admin.subscriptions.show
PUT    /panel/subscriptions/{subscription}                  → saas-admin.subscriptions.update

# Plans
GET    /panel/plans                                         → saas-admin.plans.index
POST   /panel/plans                                         → saas-admin.plans.store
GET    /panel/plans/create                                  → saas-admin.plans.create
GET    /panel/plans/{plan}                                  → saas-admin.plans.show
PUT    /panel/plans/{plan}                                  → saas-admin.plans.update
DELETE /panel/plans/{plan}                                  → saas-admin.plans.destroy
GET    /panel/plans/{plan}/edit                             → saas-admin.plans.edit

# Users
GET    /panel/users                                         → saas-admin.users.index
POST   /panel/users                                         → saas-admin.users.store
GET    /panel/users/create                                  → saas-admin.users.create
GET    /panel/users/{user}                                  → saas-admin.users.show
PUT    /panel/users/{user}                                  → saas-admin.users.update
DELETE /panel/users/{user}                                  → saas-admin.users.destroy
GET    /panel/users/{user}/edit                             → saas-admin.users.edit

# Alerts
GET    /panel/alerts                                        → saas-admin.alerts.index
POST   /panel/alerts                                        → saas-admin.alerts.store
GET    /panel/alerts/create                                 → saas-admin.alerts.create
PUT    /panel/alerts/{alert}                                → saas-admin.alerts.update
DELETE /panel/alerts/{alert}                                → saas-admin.alerts.destroy
GET    /panel/alerts/{alert}/edit                           → saas-admin.alerts.edit
POST   /panel/alerts/{alert}/resolve                        → saas-admin.alerts.resolve

# Usage
GET    /panel/usage                                         → saas-admin.usage.index

# Maintenance
GET    /panel/maintenance                                   → saas-admin.maintenance.index
POST   /panel/maintenance/{organization}/toggle             → saas-admin.maintenance.toggle

# Channel Forms
GET    /panel/channel-forms                                 → saas-admin.channel-forms.index
POST   /panel/channel-forms                                 → saas-admin.channel-forms.store
GET    /panel/channel-forms/create                          → saas-admin.channel-forms.create
GET    /panel/channel-forms/{channelForm}                   → saas-admin.channel-forms.show
DELETE /panel/channel-forms/{channelForm}                    → saas-admin.channel-forms.destroy
POST   /panel/channel-forms/{channelForm}/toggle            → saas-admin.channel-forms.toggle
```

---

## 8. ARCHIVOS DEL PROYECTO

### Models (35+)
```
Organization, Branch, Brand, User
Channel, ChannelForm, Conversation, ConversationAssignment, ConversationTransfer, ConversationSlaLog, Message
Pipeline, PipelineStage, Deal, DealStageHistory, DealNote, DealAttachment, DealCommission, Tag
Contact
KbCategory, KbArticle, KbVersion
Plan, PlanFeature, PlanFeatureValue, OrganizationSubscription, OrganizationUsageMonthly
Campaign, CampaignRecipient, DripSequence, DripSequenceStep, DripEnrollment
AutomationRule (automation_logs handled inline)
SaasAlert, AuditLog
```

### Services (15+)
```
AiAnswerService        — RAG: genera respuestas con contexto de KB
AnalyticsService       — Métricas por periodo (conversaciones, agentes, CRM, SLA)
AuditService           — Log de auditoría (auth, model changes)
AutomationService      — Auto-assign, auto-response, stale alerts
BillingService         — Subscriptions, features, limits, usage
CampaignService        — Broadcast campaigns (create, send, cancel, stats)
ContactService         — CRUD contactos, merge, CSV import
DealService            — CRM deals (create, move stages, notes, attachments)
DripService            — Drip sequences (create, enroll, process steps)
EmbeddingService       — Genera embeddings via OpenAI API
KnowledgeBaseService   — KB articles CRUD con versionamiento
PerformanceMonitorService — Queue backlog, failed jobs, stale deals
StripeService          — Checkout, portal, webhooks Stripe
VectorSearchService    — Búsqueda vectorial pgvector + keyword fallback
WebchatTokenService    — Tokens para sesiones webchat
WhatsAppService        — Envío de mensajes WhatsApp
WhatsAppWebhookService — Procesamiento de webhooks WhatsApp
```

### Policies (18+)
```
TenantPolicy (base), OrganizationPolicy, UserPolicy
BranchPolicy, BrandPolicy, ChannelPolicy
ConversationPolicy, ConversationAssignmentPolicy, ConversationTransferPolicy, ConversationSlaLogPolicy
MessagePolicy, DealPolicy, DealNotePolicy, PipelinePolicy
KbArticlePolicy, KbCategoryPolicy
ContactPolicy, CampaignPolicy, DripSequencePolicy, AutomationRulePolicy
```

### Jobs
```
SendWhatsAppMessage      — Cola: critical
SendCampaignMessage      — Cola: default
ProcessDripStep          — Cola: default (scheduled)
GenerateArticleEmbedding — Cola: low
CheckStaleConversations  — Cola: default
```

### Events
```
ConversationCreated, ConversationAssignedEvent, ConversationClosedEvent
ConversationReopenedEvent, ConversationTransferredEvent
MessageReceivedEvent, MessageSentEvent
TenantBroadcastEvent
```

### Middleware
```
CheckFeature             — Verifica feature habilitada por plan
CheckUsageLimit          — Verifica límite de uso no excedido
EnsureActiveSubscription — Requiere suscripción activa
EnsureProductionSafety   — Alerta si APP_DEBUG=true en producción
ResolveSaaSAdmin         — Valida acceso al panel admin
ResolveTenant            — Resuelve tenant por subdominio
ResolveUserTenant        — Resuelve tenant por usuario autenticado
SecurityHeaders          — Headers de seguridad (CSP, HSTS, etc.)
```

### Migrations (39 archivos)
```
0001_01_01_000000_create_users_table.php
0001_01_01_000001_create_cache_table.php
0001_01_01_000002_create_jobs_table.php
2026_01_01_000001_create_organizations_table.php
2026_01_01_000002_create_branches_table.php
2026_01_01_000003_create_personal_access_tokens_table.php
2026_01_01_000004_create_permission_tables.php
2026_01_01_001001_create_channels_table.php
2026_01_01_001002_create_conversations_table.php
2026_01_01_001003_create_messages_table.php
2026_01_01_001004_create_conversation_assignments_table.php
2026_01_01_001005_create_conversation_transfers_table.php
2026_01_01_001006_create_conversation_sla_logs_table.php
2026_01_01_001007_create_channel_forms_table.php
2026_01_01_002001_create_plans_table.php
2026_01_01_002002_create_plan_features_table.php
2026_01_01_002003_create_plan_feature_values_table.php
2026_01_01_002004_create_organization_subscriptions_table.php
2026_01_01_002005_create_organization_usage_monthly_table.php
2026_01_01_003001_create_saas_alerts_table.php
2026_01_01_004001_create_pipelines_table.php
2026_01_01_004002_create_pipeline_stages_table.php
2026_01_01_004003_create_tags_table.php
2026_01_01_004004_create_deals_table.php
2026_01_01_004005_create_deal_tag_table.php
2026_01_01_004006_create_deal_stage_history_table.php
2026_01_01_004007_create_deal_notes_table.php
2026_01_01_004008_create_deal_attachments_table.php
2026_01_01_004009_create_deal_commissions_table.php
2026_01_01_005001_create_kb_categories_table.php
2026_01_01_005002_create_kb_articles_table.php
2026_01_01_005003_create_kb_versions_table.php
2026_01_01_006001_create_audit_logs_table.php
2026_01_01_007001_create_contacts_table.php
2026_01_01_007002_create_campaigns_tables.php
2026_01_01_007003_create_drip_tables.php
2026_01_01_008001_create_automation_rules_table.php
2026_03_09_100000_create_brands_table.php
```

### Seeders
```
DatabaseSeeder.php
RolesAndPermissionsSeeder.php    — Roles + todos los permisos por módulo
PlansAndFeaturesSeeder.php       — 3 planes + features + valores por plan
```

### Factories (36)
```
AutomationRuleFactory, BranchFactory, BrandFactory, CampaignFactory, CampaignRecipientFactory
ChannelFactory, ChannelFormFactory, ContactFactory, ConversationAssignmentFactory
ConversationFactory, ConversationSlaLogFactory, ConversationTransferFactory
DealAttachmentFactory, DealCommissionFactory, DealFactory, DealNoteFactory, DealStageHistoryFactory
DripEnrollmentFactory, DripSequenceFactory, DripSequenceStepFactory
KbArticleFactory, KbCategoryFactory, KbVersionFactory
MessageFactory, OrganizationFactory, OrganizationSubscriptionFactory, OrganizationUsageMonthlyFactory
PipelineFactory, PipelineStageFactory, PlanFactory, PlanFeatureFactory, PlanFeatureValueFactory
SaasAlertFactory, TagFactory, UserFactory
```

### Tests (63 archivos, 663 tests, 1331 assertions)
```
Feature/: AdminCrudTest, AiLayerTest, AnalyticsTest, AuthenticationTest, AutomationTest,
  BillingTest, BillingUxTest, BranchTest, BrandTest, BroadcastEventTest, CampaignTest,
  ChannelFormTest, ChannelManagementTest, ChannelTest, ContactTest,
  ConversationAssignmentTest, ConversationSlaLogTest, ConversationTest, ConversationTransferTest,
  DealBoardTest, DealServiceTest, DealStalenessTest, DealTest, DripSequenceTest,
  HardeningTest, HealthCheckTest, InboxTest, KbArticleTest, KbBillingTest, KbCategoryTest,
  KnowledgeBaseServiceTest, KnowledgeBaseUiTest, MessageTest, MonitoringTest,
  OrganizationScopeTest, OrganizationTest, PipelineTest, ProductionReadinessTest,
  ResolveTenantTest, RolePolicyTest, SaaSAdminTest, SecurityAuditTest, SecurityHardeningTest,
  StripeIntegrationTest, TenantIsolationTest, TenantSettingsTest, UserPolicyTest,
  WebchatTest, WhatsAppOutboundTest, WhatsAppWebhookTest
  + Jetstream defaults (ApiTokenPermissions, BrowserSessions, DeleteAccount, EmailVerification, etc.)
```

---

## 9. FASES COMPLETADAS (1-16)

| Fase | Descripción | Tests |
|---|---|---|
| 1 | Multi-Tenant Core (organizations, branches, scopes, policies) | — |
| 2 | Auth + Roles (Jetstream 2FA, 4 roles Spatie, permissions) | — |
| 3 | Conversations (channels, messages, assignments, transfers, SLA) | — |
| 4 | WhatsApp (webhooks, inbound/outbound, multi-number) | — |
| 5 | Webchat (widget, token, realtime, rate limiting) | — |
| 6 | SaaS Billing (plans, features, subscriptions, usage, middleware) | 47 tests |
| 7 | Admin Backoffice (dashboard, CRUDs, alerts, maintenance) | 56 tests |
| 7.5 | Form Templates (channel_forms, public API, backoffice) | 32 tests |
| 8 | Monitoring (Horizon, health checks, performance monitor) | 28 tests |
| 9 | CRM Foundation (pipelines, deals, tags, notes, commissions) | 64 tests |
| 10 | AI Preparation (KB, pgvector embeddings, OpenAI integration) | 54 tests |
| 11.1-11.6 | UI (inbox, kanban, settings, billing UX, KB UI, AI layer) | 53 tests |
| 11.7 | Analytics Dashboard (Chart.js, CSV export, feature gating) | 11 tests |
| 11.8 | Security Hardening (CSP, audit logs, XSS, rate limiting, CORS) | 10 tests |
| 12.1 | Contacts (CRUD, merge, CSV import, billing limit) | 17 tests |
| 12.2 | Broadcast Campaigns (create, schedule, send, cancel, recipients) | tests |
| 12.3 | Drip Sequences (create, steps, enroll, activate/pause) | tests |
| 13 | Automations (auto-assign, auto-response, stale alerts, UI) | tests |
| 14 | Stripe Integration (StripeService HTTP, checkout, portal, webhooks) | tests |
| 15 | Production Readiness (deploy command, .env templates, CSP hardening) | tests |
| 16 | Brands (multi-brand arch, brand_id on entities, cross-brand AI search) | tests |

---

## 10. INTEGRACIONES EXTERNAS

Todas funcionan con **graceful degradation** (sin credenciales el sistema opera normalmente en modo manual).

| Integración | Estado | Descripción |
|---|---|---|
| WhatsApp Business API (Meta) | Funcional | Webhooks + envío de mensajes |
| Instagram Graph API (Meta) | Misma infra que WhatsApp | Pendiente activar |
| Facebook Messenger API (Meta) | Misma infra que WhatsApp | Pendiente activar |
| OpenAI API | Funcional | Embeddings (text-embedding-3-small) + Chat Completions (GPT-4) para RAG |
| Stripe | Funcional | Checkout sessions, Customer Portal, Webhooks (HTTP directo sin Cashier) |
| Laravel Reverb | Funcional | WebSockets para realtime (mensajes, conversaciones) |

---

## 11. PRODUCCIÓN

- **Dominio**: app.chatme.com.mx
- **Primer deploy**: 2026-03-12
- **Deploy command**: `php artisan app:deploy` (git pull, composer install, migrate, cache, horizon:terminate)
- **Setup inicial**: key:generate, storage:link, seed RolesAndPermissionsSeeder + PlansAndFeaturesSeeder

### Issue conocido:
- **Alpine.js no funciona en producción**: Livewire 3.x debería inyectar Alpine via @livewireScripts pero no ejecuta en producción. Workaround: usar vanilla JS para UI interactiva. TODO: investigar causa raíz.

---

## 12. DECISIONES ARQUITECTÓNICAS

1. **Contacts table separada**: directorio unificado con link a conversations y deals via contact_id FK
2. **Campaigns**: broadcast masivo + drip sequences automatizadas
3. **Automations**: auto-assign (round-robin, least-busy), auto-response (horarios), stale deal alerts. **Excluido**: routing por keywords
4. **Feature gating**: TODAS las features toggle-ables por plan, registradas en PlansAndFeaturesSeeder
5. **Migration consolidation**: max 7 archivos en desarrollo, ALTER solo en producción
6. **External integrations**: graceful degradation sin credenciales
7. **Stripe sin Cashier**: integración directa HTTP para mayor control
8. **Brands multi-marca**: brand_id nullable en branches, channels, conversations, KB articles/categories; búsqueda vectorial cross-brand con pesos de prioridad (misma marca 1.0, global 0.9, otras marcas 0.8)

---

## 13. REGLAS DE DESARROLLO

### Prioridad de decisiones técnicas:
1. Seguridad
2. Aislamiento multi-tenant
3. Feature gating (todo respeta el plan)
4. Mantenibilidad
5. Performance
6. Velocidad de desarrollo

### Naming conventions:
- Controllers: PascalCase singular (DealController)
- Services: PascalCase + Service (DealService)
- Policies: PascalCase + Policy (DealPolicy)
- Migrations: snake_case descriptivo
- Feature codes: snake_case (max_agents, crm_enabled)

### Testing patterns:
- Tests con policies: seed `RolesAndPermissionsSeeder`
- Tests con billing: seed `PlansAndFeaturesSeeder`
- Tests con broadcasting: `Event::fake([BroadcastEvent::class])` en setUp
- Carbon: `$earlier->diffInSeconds(now(), false)` para tiempo positivo
- Cross-tenant: `withoutGlobalScopes()` en cada model + subqueries

### Prohibido:
- Romper aislamiento multi-tenant
- Crear migraciones innecesarias
- Sobre-ingenierizar
- Features fuera del roadmap
- Funcionalidad sin feature gating
- Hardcodear credenciales
- Saltar billing para features de pago

---

## 14. PENDIENTE (Fase 15 - Production Readiness)

El checklist muestra estas tareas pendientes de la Fase 15:

- [ ] 15.1 Migration Consolidation (consolidar en max 7 archivos)
- [ ] 15.2 Environment Configuration (.env.example completo, Docker Compose, docs)
- [ ] 15.3 Performance Optimization (eager loading, indices, cache, Horizon tuning)
- [ ] 15.4 Security Final Review (OWASP, dependency audit, secrets rotation, backups)
- [ ] 15.5 Deploy (CI/CD GitHub Actions, provisioning guide, SSL, monitoring, rollback)

---

*Documento generado el 2026-03-14. Total: 663 tests, 1331 assertions, 224 rutas, 35+ modelos, 15+ servicios, 18+ policies.*
