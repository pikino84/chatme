
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
[x] PlansAndFeaturesSeeder (3 plans, 9 features)
[x] tests (47 tests, 87 assertions)
[ ] stripe gateway (deferred — system works with manual subscriptions)
[ ] stripe webhooks (deferred)

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
[ ] Analytics
[ ] Security Hardening Improvements
