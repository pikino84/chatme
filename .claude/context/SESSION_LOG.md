# Session Log

Use this file to track architectural decisions made during AI sessions.

## 2026-03-09 — Roadmap Expansion & Context Files Overhaul

### Decisions Made:
1. **Contacts table**: YES — separate `contacts` table with unified contact directory. Conversations and deals will link via contact_id FK.
2. **Campaigns**: Both broadcast (mass send) AND drip sequences (automated multi-step).
3. **Automations**: Auto-assignment (round-robin, least-busy), auto-responses (schedule-based), stale deal alerts. Explicitly EXCLUDED: keyword-based workflow routing ("if message contains X, assign to agent Y").
4. **Feature gating**: ALL features must be toggle-able by plan. Every new module registers features in PlansAndFeaturesSeeder.
5. **Migration consolidation**: Target max 7 migration files during development. Only use ALTER migrations in production.
6. **External integrations**: Must work without credentials (graceful degradation). System usable end-to-end without Meta, OpenAI, or Stripe keys.

### Phases Added:
- Phase 11.7: Analytics Dashboard
- Phase 11.8: Security Hardening
- Phase 12: Contacts + Campaigns (broadcast + drip)
- Phase 13: Automations (auto-assign, auto-response, stale alerts)
- Phase 14: Stripe Integration (checkout, webhooks, admin)
- Phase 15: Production Readiness (consolidation, config, performance, security, deploy)

### Files Updated:
- CHATME_SYSTEM_PROMPT.md — added feature gating rules, naming conventions, migration consolidation targets, integration rules
- DATABASE_SCHEMA_MASTER.md — complete rewrite with all existing + planned tables
- PROJECT_CONTEXT.md — expanded with business model, target market, architecture decisions
- PROJECT_CHECKLIST.md — added phases 11.7 through 15 with detailed sub-tasks
- SESSION_LOG.md — this entry
