# ChatMe -- PHASE 11 PRODUCT LAYER EXECUTION PLAN

Generated: 2026-03-01 20:51:22 UTC

------------------------------------------------------------------------

## CURRENT PHASE

PHASE 11 -- PRODUCT LAYER

Objective: Transform the existing enterprise-ready multi-tenant
infrastructure into a usable SaaS product UI layer without breaking
existing architecture.

------------------------------------------------------------------------

# RULES (MANDATORY)

-   Maintain strict multi-tenant isolation
-   Enforce all existing policies
-   Do NOT modify admin backoffice
-   Do NOT break existing tests (884+ assertions must continue passing)
-   Add minimal new tests per module
-   Respect billing middleware
-   Respect feature limits
-   Keep code modular
-   Thin UI layer only (no domain logic rewrite)

------------------------------------------------------------------------

# EXECUTION ORDER

1.  Conversations Inbox UI
2.  CRM Kanban UI
3.  Tenant Settings
4.  Billing UX
5.  Knowledge Base UI
6.  AI Layer
7.  Analytics
8.  Security Hardening Improvements

------------------------------------------------------------------------

# IMMEDIATE TASK

## Conversations Inbox UI

Scope:

-   InboxController (tenant)
-   ConversationsController (tenant)
-   MessageController (UI layer only)
-   Paginated conversations endpoint
-   Mark as read endpoint
-   Assignment endpoint
-   Transfer endpoint
-   3-column inbox layout (Blade)
-   Realtime updates via Reverb (listen only)
-   Attachments preview UI
-   Metadata drawer UI

Constraints:

-   Must use existing models and policies
-   Must rely on global tenant scope
-   Must apply feature middleware
-   Must not modify admin routes
-   Must not refactor backend services

------------------------------------------------------------------------

# CLAUDE EXECUTION INSTRUCTION

Use this exact instruction when invoking Claude:

------------------------------------------------------------------------

You are implementing PHASE 11 -- Conversations Inbox UI.

Context: ChatMe is an enterprise-grade multi-tenant SaaS in Laravel.
Infrastructure is complete (Phases 1--10). All tests are passing. Do NOT
modify domain logic. Do NOT touch admin backoffice. Do NOT refactor
services.

Task: Implement ONLY the Conversations Inbox UI layer.

Requirements: - Tenant routes only - Controllers limited to UI
orchestration - Strict policy enforcement - Respect billing feature
middleware - Paginated inbox - Mark as read - Assign & transfer
endpoints - 3-column Blade layout - Reverb listener hooks - Minimal new
tests

Goal: Intervene as little as possible. Do not rewrite existing
architecture. Add thin UI layer only. Keep implementation fast and
minimal.

Return: - Routes - Controllers - Blade structure - Minimal tests -
Checklist update - Risk report - Next step suggestion

------------------------------------------------------------------------

# PROJECT LOCATION

Place this file in:

/docs/architecture/PHASE_11_PRODUCT_LAYER.md

If the /docs/architecture directory does not exist, create it.

------------------------------------------------------------------------

END OF DOCUMENT
