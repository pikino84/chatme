
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

## Phase 7.5 – Form Templates System
[x] channel_forms table + metadata column on conversations
[x] config/form_templates.php (contacto_basico, muebleria, agencia_viajes)
[x] ChannelForm model + factory + Channel→hasOne relation
[x] Public endpoint: GET /api/webchat/{uuid}/form-schema (origin validation)
[x] Form data saved in conversation.metadata (source=widget_form, contact_name from form)
[x] Backoffice: ChannelFormController (index, create, show, toggle, delete)
[x] Blade views (index, create, show) + sidebar link
[x] tests (32 tests, 79 assertions)

## Phase 8 – Monitoring
[ ] horizon monitoring
[ ] netdata setup
[ ] performance thresholds

## Phase 9 – CRM Foundation
[ ] pipelines
[ ] pipeline stages
[ ] deals table

## Phase 10 – AI Preparation
[ ] knowledge base table
[ ] embedding column placeholder
