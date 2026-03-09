# Project Context

ChatMe is a conversational CRM SaaS focused on WhatsApp and social channels.

## Business Model
- Multi-tenant SaaS with 3 plans: Starter ($499/mo), Professional ($999/mo), Enterprise ($2,499/mo)
- Every feature can be toggled on/off per plan via feature gating system
- Revenue: subscriptions + optional per-usage charges on metered features

## Target Market
- SMBs in Mexico using WhatsApp as primary customer channel
- Sales teams needing CRM integrated with messaging
- Customer support teams managing multi-channel conversations

## Core Value Proposition
1. Unified inbox for WhatsApp, Instagram, Facebook, Webchat
2. Convert conversations into CRM deals with pipelines
3. AI-powered answers from knowledge base (RAG)
4. Campaign management (broadcast + drip sequences)
5. Automation of repetitive tasks (auto-assign, auto-reply, scheduled responses)

## Architecture Decisions
- Monolithic modular (not microservices) — appropriate for current scale
- PostgreSQL single-database multi-tenant with organization_id column isolation
- pgvector for AI embeddings (with keyword fallback when extension unavailable)
- Queue-based async processing with 3 priority tiers (critical/default/low)
- All external integrations (Meta APIs, OpenAI, Stripe) work gracefully without credentials

## Key Technical Patterns
- Tenant isolation: OrganizationScope global scope + BelongsToOrganization trait
- Authorization: TenantPolicy base class + Spatie permissions
- Billing: plan_features catalog + BillingService + middleware gates
- Services: business logic in Service classes, controllers only coordinate
- Testing: 526+ tests, tenant isolation verified in every feature

## Current Status
- Phases 1-11.6 completed (core platform functional)
- Phases 11.7-11.8 pending (analytics, security hardening)
- Phases 12-15 planned (contacts, campaigns, automations, stripe, deploy)
- System usable end-to-end without external credentials (graceful degradation)
