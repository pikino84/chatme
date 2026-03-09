# DATABASE_SCHEMA_MASTER.md

Convención: todas las tablas tenant incluyen organization_id, created_at, updated_at.
Tablas globales (plans, plan_features, cache, jobs) NO tienen organization_id.

---

## Core Tables

### organizations
- id, name, slug (unique), status (enum: active/suspended/trial), settings (jsonb), timestamps
- settings jsonb almacena: maintenance_mode, ai_enabled, ai_model, ai_temperature, logo, timezone

### branches
- id, organization_id (FK cascade), name, address, phone, is_active, timestamps

### users
- id, organization_id (nullable FK nullOnDelete), name, email (unique), email_verified_at, password
- two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at
- remember_token, current_team_id, profile_photo_path, is_active (boolean default true), timestamps

### password_reset_tokens
- email (PK), token, created_at

### sessions
- id (PK string), user_id (FK), ip_address, user_agent, payload, last_activity

---

## Conversations Tables

### channels
- id, uuid (unique), organization_id (FK cascade), type (enum: whatsapp/webchat/email/facebook/instagram), name, configuration (text), is_active, timestamps
- configuration: almacena verify_token, app_secret, phone_number_id, access_token según el tipo

### conversations
- id, organization_id (FK cascade), channel_id (FK restrict), assigned_user_id (nullable FK nullOnDelete), branch_id (nullable FK nullOnDelete)
- status (enum: open/pending/closed), subject, contact_name, contact_identifier
- priority (enum: low/normal/high/urgent), metadata (jsonb), closed_at, last_message_at, timestamps
- contact_id (FK nullable) — pendiente Phase 12

### messages
- id, organization_id (FK cascade), conversation_id (FK cascade), user_id (nullable FK nullOnDelete)
- type (enum: text/image/audio/video/document/template), body (text), external_id, metadata (jsonb)
- direction (enum: inbound/outbound), timestamps

### conversation_assignments
- id, organization_id (FK cascade), conversation_id (FK cascade), user_id (FK cascade)
- assigned_by (nullable FK nullOnDelete), timestamps

### conversation_transfers
- id, organization_id (FK cascade), conversation_id (FK cascade)
- from_user_id (FK cascade), to_user_id (FK cascade), reason (text), timestamps

### conversation_sla_logs
- id, organization_id (FK cascade), conversation_id (FK cascade)
- metric (string), target_seconds (int), actual_seconds (int nullable), breached (boolean default false), timestamps

### channel_forms
- id, organization_id (FK cascade), channel_id (FK restrict), template_key (string), is_active, timestamps

---

## CRM Tables

### pipelines
- id, organization_id (FK cascade), name, is_default (boolean), timestamps

### pipeline_stages
- id, organization_id (FK cascade), pipeline_id (FK cascade), name, position (int), is_won (boolean), is_lost (boolean), max_duration_hours (int nullable), timestamps

### tags
- id, organization_id (FK cascade), name, timestamps
- unique constraint: [organization_id, name]

### deals
- id, organization_id (FK cascade), pipeline_id (FK restrict), pipeline_stage_id (FK restrict)
- conversation_id (nullable FK nullOnDelete), assigned_user_id (nullable FK nullOnDelete)
- contact_name, contact_email, contact_phone
- value (decimal 12,2), currency (string 3 default MXN), stage_entered_at
- status (enum: open/won/lost), expected_close_date, closed_at, timestamps
- contact_id (FK nullable) — pendiente Phase 12

### deal_tag (pivot)
- deal_id (FK cascade), tag_id (FK cascade)

### deal_stage_history
- id, organization_id (FK cascade), deal_id (FK cascade)
- from_stage_id (nullable FK nullOnDelete), to_stage_id (FK restrict)
- changed_by (nullable FK nullOnDelete), changed_at (timestamp), timestamps

### deal_notes
- id, organization_id (FK cascade), deal_id (FK cascade), user_id (FK cascade), body (text), timestamps

### deal_attachments
- id, organization_id (FK cascade), deal_id (FK cascade), user_id (FK cascade)
- file_name, file_path, file_size (int), mime_type, timestamps

### deal_commissions
- id, organization_id (FK cascade), deal_id (FK cascade), user_id (FK cascade)
- amount (decimal 10,2), percentage (decimal 5,2 nullable), status (enum: pending/paid/canceled), paid_at (nullable), timestamps

---

## Contacts Tables (Phase 12 — pendiente)

### contacts
- id, organization_id (FK cascade), name, email (nullable), phone (nullable)
- external_id (nullable, para WhatsApp/FB/IG ID), channel_type (nullable)
- company (nullable), notes (text nullable), metadata (jsonb nullable)
- timestamps
- unique constraint: [organization_id, phone] y [organization_id, email] (nullable unique)

---

## AI / Knowledge Base Tables

### kb_categories
- id, organization_id (FK cascade), name, description (text nullable), position (int), parent_id (self-referencing nullable FK), is_active, timestamps

### kb_articles
- id, organization_id (FK cascade), kb_category_id (FK restrict), created_by (FK), updated_by (FK)
- title, slug, content (text), status (enum: draft/published/archived), priority (int)
- visible_on_webchat, visible_on_whatsapp, visible_on_instagram, visible_on_facebook (booleans)
- published_at (nullable), embedding (vector 1536, pgvector, nullable), timestamps

### kb_versions
- id, organization_id (FK cascade), kb_article_id (FK cascade), version_number (int)
- title, content (text), changed_by (FK), change_summary (text nullable), timestamps

---

## Billing Tables (globales — sin organization_id)

### plans
- id, name, slug (unique), description (text), price_monthly (unsigned int), price_yearly (unsigned int)
- is_active (boolean), sort_order (smallint), trial_days (smallint), timestamps

### plan_features
- id, code (unique), description, type (enum: boolean/limit), timestamps

### plan_feature_values
- id, plan_id (FK cascade), plan_feature_id (FK cascade), value (string), timestamps

### organization_subscriptions
- id, organization_id (FK cascade), plan_id (FK restrict)
- status (enum: active/trialing/canceled/expired), billing_cycle (enum: monthly/yearly)
- starts_at, ends_at, trial_ends_at (nullable), canceled_at (nullable), grace_period_ends_at (nullable), timestamps

### organization_usage_monthly
- id, organization_id (FK cascade), feature_code (string), period (string YYYY-MM), usage (int), timestamps

---

## Admin Tables

### saas_alerts
- id, organization_id (nullable FK), type (string), message (text), severity (enum), resolved (boolean), created_by (nullable FK), timestamps

---

## Campaigns Tables (Phase 12 — pendiente)

### campaigns
- id, organization_id (FK cascade), channel_id (FK restrict), name, type (enum: broadcast/drip)
- status (enum: draft/scheduled/running/completed/canceled)
- message_template (text), scheduled_at (nullable), completed_at (nullable)
- total_recipients (int default 0), sent_count (int default 0), failed_count (int default 0)
- timestamps

### campaign_recipients
- id, campaign_id (FK cascade), contact_id (FK cascade)
- status (enum: pending/sent/delivered/failed), sent_at (nullable), error_message (nullable)
- timestamps

### drip_sequences
- id, organization_id (FK cascade), name, status (enum: active/paused/archived)
- trigger_event (string), timestamps

### drip_sequence_steps
- id, drip_sequence_id (FK cascade), position (int), delay_minutes (int)
- message_template (text), channel_type (enum: whatsapp/webchat/facebook/instagram)
- timestamps

### drip_enrollments
- id, drip_sequence_id (FK cascade), contact_id (FK cascade)
- current_step (int default 0), status (enum: active/completed/canceled)
- next_step_at (nullable), timestamps

---

## Automations Tables (Phase 13 — pendiente)

### automations
- id, organization_id (FK cascade), name, type (enum: auto_assign/auto_response/schedule_response/stale_deal_alert)
- is_active (boolean default false), configuration (jsonb), schedule (jsonb nullable)
- timestamps

### automation_logs
- id, automation_id (FK cascade), organization_id (FK cascade)
- trigger_type (string), trigger_id (nullable bigint), result (enum: success/failure/skipped)
- details (jsonb nullable), timestamps

---

## Infrastructure Tables (Laravel framework)

### cache, cache_locks
- Standard Laravel cache tables

### jobs, job_batches, failed_jobs
- Standard Laravel queue tables

### personal_access_tokens
- Standard Laravel Sanctum table
