
# PROJECT_CHECKLIST.md

## Phase 1 – Multi-Tenant Core
[x] organizations
[x] branches
[x] subdomain resolver
[x] global tenant scope
[x] tenant policies

## Phase 2 – Auth + Roles
[x] Jetstream + 2FA
[x] Spatie roles
[x] role policies

## Phase 3 – Conversations
[x] conversations table
[x] channels table
[x] messages table
[x] assignments
[x] transfers
[x] SLA logs
[x] realtime events

## Phase 4 – WhatsApp
[x] webhook validation
[x] incoming messages
[x] outgoing messages
[x] multi-number support

## Phase 5 – Webchat
[x] widget script
[x] session token
[x] realtime messaging
[x] rate limiting

## Phase 6 – SaaS Billing
[x] plans + plan_features + plan_feature_values
[x] organization_subscriptions
[x] BillingService (subscribe, cancel, changePlan, checkFeature, checkLimit, usage)
[x] billing middleware (subscription, feature, usage.limit)
[x] PlansAndFeaturesSeeder (3 plans, 12 features)
[x] tests (47 tests, 87 assertions)
[ ] stripe gateway (deferred to Phase 14)
[ ] stripe webhooks (deferred to Phase 14)

## Phase 7 – Admin Backoffice
[x] saas_admin role + ResolveSaaSAdmin middleware
[x] subdomain routing (admin.chatme.test/panel) + Blade layout
[x] DashboardController (stats: orgs, subs, revenue, alerts)
[x] OrganizationController (index, show, edit, suspend/activate)
[x] SubscriptionController (index, show, update plan/status/cycle)
[x] UsageController (usage per org per period)
[x] AlertController + saas_alerts table (CRUD, resolve, global/per-org)
[x] MaintenanceController (toggle per org via settings)
[x] ResolveTenant updated for maintenance_mode check
[x] tests (35 tests, 78 assertions)
[x] UserController CRUD (index with search/filter, show, create, store, edit, update, destroy + self-delete protection)
[x] PlanController CRUD (index, show, create, store, edit, update, destroy + subscription protection + feature values sync)
[x] OrganizationController expanded (create, store, destroy + user protection)
[x] SubscriptionController expanded (create, store with auto ends_at/trial_ends_at)
[x] Admin views: users (index/show/form), plans (index/show/form), organizations/form, subscriptions/create
[x] Sidebar updated with Users + Plans links
[x] Admin CRUD tests (21 tests, 49 assertions)

## Phase 7.5 – Form Templates System
[x] channel_forms table + metadata column on conversations
[x] config/form_templates.php (contacto_basico, muebleria, agencia_viajes)
[x] ChannelForm model + factory + Channel→hasOne relation
[x] Public endpoint: GET /api/webchat/{uuid}/form-schema (origin validation)
[x] Form data saved in conversation.metadata (source=widget_form, contact_name from form)
[x] Backoffice: ChannelFormController (index, create, show, toggle, delete)
[x] Blade views (index, create, show) + sidebar link
[x] tests (32 tests, 79 assertions)

## Phase 8 – Monitoring & Hardening
[x] Laravel Horizon installed + configured (critical/default/low queues, saas_admin gate)
[x] QUEUE_CONNECTION switched to redis
[x] SendWhatsAppMessage dispatched to critical queue
[x] PerformanceMonitorService (queue backlog, failed jobs, usage >90% → auto SaasAlert)
[x] monitor:performance artisan command + scheduled every 5 minutes
[x] saas_alerts.created_by made nullable for system-generated alerts
[x] Health check endpoints: /health/app, /health/db, /health/redis, /health/queue
[x] EnsureProductionSafety middleware (logs critical if APP_DEBUG=true in production)
[x] Reverb allowed_origins configurable via REVERB_ALLOWED_ORIGINS env
[x] Horizon link in admin sidebar
[x] tests (28 tests, 55 assertions)
[ ] netdata setup (deferred — infrastructure-level, not application code)

## Phase 9 – CRM Foundation
[x] pipelines + pipeline_stages tables (position ordering, is_won/is_lost, max_duration_hours SLA)
[x] deals table (pipeline/stage/conversation/assigned_user, value, currency, status, stage_entered_at)
[x] deal_tag pivot, tags table (unique per org)
[x] deal_stage_history (from/to stage, changed_by, changed_at)
[x] deal_notes, deal_attachments, deal_commissions tables
[x] 8 models (Pipeline, PipelineStage, Deal, DealStageHistory, DealNote, DealAttachment, DealCommission, Tag)
[x] Existing models updated: Conversation→deals(), Organization→pipelines()/deals()/tags(), User→deals()/commissions()
[x] 8 factories with chainable states (won/lost/stale/highValue/assigned/etc.)
[x] DealPolicy, PipelinePolicy, DealNotePolicy (tenant-aware, role-based)
[x] 11 CRM permissions added to RolesAndPermissionsSeeder (org_admin/supervisor/agent)
[x] DealService (convertToDeal, createDeal, moveToStage, setDefaultPipeline, addNote, addAttachment)
[x] PerformanceMonitorService.checkDealStaleness() — SLA alerts for stale deals
[x] tests (64 new tests, 805 total assertions)

## Phase 10 – AI Preparation
[x] kb_categories table (org_id, name, description, position, parent_id self-referencing, is_active)
[x] kb_articles table (org_id, category, created_by/updated_by, title, slug, content, status enum, priority, visible_on_webchat/whatsapp/instagram/facebook, published_at)
[x] kb_versions table (org_id, article_id, version_number, title, content, changed_by, change_summary)
[x] pgvector embedding column vector(1536) on kb_articles (try/catch for envs without pgvector)
[x] 3 models (KbCategory, KbArticle, KbVersion) + Organization updated
[x] 3 factories with chainable states (published/archived/visibleOnWebchat/visibleOnWhatsApp/inactive)
[x] KbArticlePolicy, KbCategoryPolicy (tenant-aware, role-based, category delete checks zero articles)
[x] 5 KB permissions (kb.view/create/update/delete/publish) added to RolesAndPermissionsSeeder
[x] kb_articles_limit feature added to PlansAndFeaturesSeeder (Starter: 20, Professional: 200, Enterprise: unlimited)
[x] KnowledgeBaseService (createArticle with billing limit, updateArticle with versioning, publish, archive, delete, getPublishedArticles with channel filter)
[x] tests (54 new tests, 884 total assertions)
[x] OpenAI integration (EmbeddingService, AiAnswerService, VectorSearchService)

## Phase 11 – Product Layer UI
[x] Conversations Inbox UI (InboxController, ConversationsController, MessageController, 3-column Blade layout, 11 tests)
[x] CRM Kanban UI (DealBoardController, DealController, kanban board + drawer + create modal, 7 tests)
[x] Tenant Settings (SettingsController, TeamController, org name/logo/timezone, user roles/activate, 8 tests)
[x] Migration: add is_active boolean to users table
[x] Billing UX (BillingController, subscription/plans/usage views, limit banners, 7 tests)
[x] Knowledge Base UI (KbCategoryController, KbArticleController, category/article CRUD, version history, 10 tests)
[x] AI Layer (EmbeddingService, VectorSearchService, AiAnswerService, GenerateArticleEmbedding job, AiConfigController, 10 tests)
[ ] 11.7 Analytics Dashboard
[ ] 11.8 Security Hardening Improvements

## Phase 11.7 – Analytics Dashboard
[x] AnalyticsService con métricas por periodo (hoy, 7d, 30d, custom)
[x] AnalyticsController (index + export CSV)
[x] Métricas de conversaciones: total, por status, por canal, por prioridad, tiempo promedio de resolución
[x] Métricas de agentes: conversaciones atendidas, cerradas, tiempo promedio resolución
[x] Métricas CRM: deals creados, ganados, perdidos, valor total/ganado, tasa de conversión, tiempo cierre promedio
[x] Métricas de mensajes: total, entrantes, salientes, volumen diario
[x] Métricas SLA: total, incumplidos, cumplidos, tasa de cumplimiento
[x] Gráficas con Chart.js (tendencia conversaciones, volumen mensajes, canal doughnut, deals doughnut)
[x] Export CSV de reportes completo (conversaciones, mensajes, deals, agentes, SLA)
[x] Feature gating: reports_enabled (Starter=false, Professional=true, Enterprise=true)
[x] Sidebar link "Reportes" en app layout
[x] Tests (11 tests, 37 assertions: acceso, permisos, feature gating, métricas, tenant isolation, CSV, filtros)

## Phase 11.8 – Security Hardening
[x] SecurityHeaders middleware (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy, HSTS, CSP)
[x] CSP configurable via env SECURITY_CSP + config/security.php
[x] Rate limiting por tenant: throttle:tenant-api (60 req/min configurable) en todas las rutas tenant
[x] Audit log system: audit_logs table, AuditLog model, AuditService (log, logAuth, logModelChange)
[x] Audit login/logout/failed via AuditLoginListener + Laravel auth events
[x] Audit role changes + user activation/deactivation en TeamController
[x] Sanitización XSS: strip_tags en MessageController body
[x] CORS config publicado: config/cors.php con allowed_origins via env, headers específicos
[x] Tests (10 tests, 27 assertions: headers, CSP, audit CRUD, audit disabled, role change audit, toggle audit, login audit, XSS, rate limiter, CORS)

## Phase 12 – Contacts + Campaigns
[x] 12.1 Contacts Module
    [x] contacts table + conversations.contact_id + deals.contact_id (FK nullable)
    [x] Contact model + BelongsToOrganization + ContactFactory
    [x] ContactPolicy (tenant-aware, permission-based)
    [x] ContactService (create with billing limit, update, delete, findOrCreateByPhone/Email, merge, importCsv)
    [x] BillingLimitException for plan enforcement
    [x] ContactController (index+search, show+history, create, edit, update, destroy, importForm, import CSV)
    [x] Views: index (search+pagination), show (info+conversations+deals), form (create/edit), import
    [x] Features: max_contacts (Starter=100, Professional=5000, Enterprise=unlimited)
    [x] Permissions: contacts.view/create/update/delete/import (org_admin all, supervisor view+create+update, agent view+create)
    [x] Sidebar link "Contactos"
    [x] Tests (17 tests, 35 assertions: CRUD, search, tenant isolation, merge, CSV import, billing limit)
[ ] 12.2 Broadcast Campaigns
    [ ] campaigns table (channel_id, name, type=broadcast, status, message_template, scheduled_at, stats)
    [ ] campaign_recipients table (campaign_id, contact_id, status, sent_at, error)
    [ ] Campaign model + CampaignRecipient model + factories
    [ ] CampaignPolicy (tenant-aware)
    [ ] CampaignService (create, schedule, send, pause, cancel, stats)
    [ ] SendCampaignMessage job (dispatched to default queue, respects rate limits)
    [ ] CampaignController + UI (create, recipient selection, preview, schedule, status dashboard)
    [ ] Features: campaigns_enabled (boolean), max_campaigns_monthly (limit)
    [ ] Permissions: campaigns.view, campaigns.create, campaigns.send, campaigns.delete
    [ ] Tests
[ ] 12.3 Drip Sequences
    [ ] drip_sequences table (name, status, trigger_event)
    [ ] drip_sequence_steps table (position, delay_minutes, message_template, channel_type)
    [ ] drip_enrollments table (contact_id, current_step, status, next_step_at)
    [ ] DripSequence, DripSequenceStep, DripEnrollment models + factories
    [ ] DripService (create, enroll contact, process next step, pause, cancel enrollment)
    [ ] ProcessDripStep job (scheduled, processes pending enrollments)
    [ ] DripController + UI (builder de secuencia, enrollment list, stats)
    [ ] Features: drip_sequences_enabled (boolean)
    [ ] Permissions: drip.view, drip.create, drip.update, drip.delete
    [ ] Tests

## Phase 13 – Automations
[ ] 13.1 Auto-Assignment
    [ ] automations table (name, type, is_active, configuration jsonb, schedule jsonb)
    [ ] automation_logs table (automation_id, trigger_type, trigger_id, result, details)
    [ ] Automation model + AutomationLog model + factories
    [ ] AutomationPolicy (tenant-aware)
    [ ] AutoAssignService (round-robin, least-busy, by-channel, by-branch)
    [ ] Trigger: on new conversation created → auto-assign agent
    [ ] Configuration: { strategy: "round_robin|least_busy", agent_ids: [...], channel_ids: [...] }
    [ ] Tests
[ ] 13.2 Auto-Responses
    [ ] AutoResponseService (respuesta automática por horario fuera de oficina)
    [ ] Configuration: { message: "...", schedule: { days: [...], start: "HH:mm", end: "HH:mm" } }
    [ ] Trigger: on new inbound message outside business hours → send auto-response
    [ ] Respeta rate limit: máximo 1 auto-respuesta por conversación por periodo
    [ ] Tests
[ ] 13.3 Stale Deal Alerts
    [ ] Extender PerformanceMonitorService.checkDealStaleness() existente
    [ ] Automation config: { pipeline_id: X, max_hours: Y, notify: "email|alert|both" }
    [ ] NotifyDealStale job → genera SaasAlert + opcionalmente email al agente asignado
    [ ] Tests
[ ] 13.4 Automations UI
    [ ] AutomationController + views (index, create/edit form por tipo, logs viewer)
    [ ] Features: automations_enabled (boolean), max_automations (limit)
    [ ] Permissions: automations.view, automations.create, automations.update, automations.delete
    [ ] Tests

## Phase 14 – Stripe Integration
[ ] 14.1 Stripe Configuration
    [ ] config/services.php stripe section (api_key, webhook_secret, publishable_key)
    [ ] StripeService (createCustomer, createSubscription, cancelSubscription, changePlan, createCheckoutSession)
    [ ] Graceful degradation: si no hay credenciales, billing funciona en modo manual (como ahora)
    [ ] organization.settings: stripe_customer_id
    [ ] Tests con mocks
[ ] 14.2 Stripe Checkout
    [ ] Checkout session creation para nuevas suscripciones
    [ ] Customer portal para gestionar método de pago
    [ ] BillingController actualizado: botón "Pagar con Stripe" o "Gestionar con Stripe"
    [ ] Tests
[ ] 14.3 Stripe Webhooks
    [ ] POST /webhooks/stripe endpoint
    [ ] Eventos: checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.updated, customer.subscription.deleted
    [ ] Sincronización automática de status de suscripción
    [ ] Signature validation
    [ ] Tests
[ ] 14.4 Admin Panel Stripe
    [ ] Vista de pagos y facturas por organización
    [ ] Indicador de modo (manual vs Stripe) en admin
    [ ] Tests

## Phase 17 – Channel Wizard + Meta Embedded Signup
[x] 17.1 Channel Connection Wizard
    [x] Wizard blade view (vanilla JS, 4 steps, no Alpine)
    [x] ChannelController: wizard(), wizardValidate(), wizardStore()
    [x] Real credential validation via Meta Graph API v21.0
    [x] Billing limit enforcement (counts real channels vs plan limit)
    [x] Brand association support
    [x] Contextual help text per channel type (where to find credentials in Meta Console)
    [x] Copy-to-clipboard for webhook URLs and verify tokens
    [x] Routes: wizard, wizard/validate, wizard/store
    [x] Tests (17 tests, 55 assertions)
[ ] 17.2 Meta Embedded Signup (next step — waiting on Meta App Review)
    [ ] Meta App: register as Tech Provider, request permissions, enable Embedded Signup
    [ ] config/services.php: meta_app_id, meta_app_secret, meta_redirect_uri
    [ ] MetaOAuthService (exchangeCodeForToken, getLongLivedToken, refreshToken, getWABAs, getPhoneNumbers, subscribeWebhook)
    [ ] MetaOAuthController: redirect(), callback() — handles OAuth code exchange
    [ ] Route: GET /auth/meta/callback
    [ ] Update channel configuration: add meta_user_id, business_id, refresh_token, token_expires_at, token_type (oauth|manual)
    [ ] Update wizard Step 2: "Conectar con Facebook" button (JS SDK popup) for WhatsApp/FB/IG, keep manual fallback
    [ ] RefreshMetaTokens job: scheduled every 12h, renew tokens expiring within 7 days, SaasAlert on failure
    [ ] Auto-subscribe webhooks programmatically after OAuth
    [ ] Backward compatible: existing manual channels (token_type=manual) keep working
    [ ] Tests

## Phase 15 – Production Readiness
[ ] 15.1 Migration Consolidation
    [ ] Consolidar todas las migraciones en máximo 7 archivos (ver CHATME_SYSTEM_PROMPT.md)
    [ ] Verificar fresh migrate + seed funciona limpio
    [ ] Tests completos pasan después de consolidación
[ ] 15.2 Environment Configuration
    [ ] .env.example completo con todas las variables necesarias
    [ ] config/ files review: todas las credenciales via env()
    [ ] Docker Compose para desarrollo (PostgreSQL, Redis, app)
    [ ] Documentación de setup para nuevos desarrolladores
[ ] 15.3 Performance Optimization
    [ ] Eager loading review en todos los controllers (N+1 queries)
    [ ] Database indices review
    [ ] Cache strategy para queries frecuentes (plans, features, org settings)
    [ ] Horizon tuning para producción
[ ] 15.4 Security Final Review
    [ ] OWASP top 10 checklist
    [ ] Dependency audit (composer audit)
    [ ] .env secrets rotation plan
    [ ] Backup strategy documentation
[ ] 15.5 Deploy
    [ ] CI/CD pipeline (GitHub Actions: tests, lint, deploy)
    [ ] Production server provisioning guide
    [ ] SSL/domain configuration
    [ ] Monitoring + alerting setup
    [ ] Rollback procedure documented
