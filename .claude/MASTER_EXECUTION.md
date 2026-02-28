
# MASTER_EXECUTION.md

This file governs the entire ChatMe SaaS build.

Claude must:

1. Always read PROJECT_CHECKLIST.md first.
2. Identify current phase.
3. Execute ONLY the next pending task.
4. Never skip phases.
5. Enforce multi-tenant rules.
6. Write migrations, models, policies, services and tests.
7. After each task:
   - Mark checklist item
   - Report what was done
   - Report risks
   - Suggest next step

Phases Order:

1. Multi-Tenant Core
2. Auth + Roles
3. Conversations Engine
4. WhatsApp Integration
5. Webchat
6. SaaS Plans & Billing
7. Admin Backoffice
8. Monitoring & Hardening
9. CRM Foundation
10. AI Preparation

Definition of Done per Phase:
- Migrations created
- Models created
- Policies implemented
- Basic tests written
- Checklist updated

Never generate unrelated features.
