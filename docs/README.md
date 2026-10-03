# News Site Generation Platform — Documentation

**Version:** 0.1.0 (pre-implementation)
**Last updated:** 2026-09-30

---

## Quick Start

This is the documentation for a **reusable platform for creating and maintaining multiple news websites from one central codebase**.

**Core principle:** Build Once → Reuse → Customize → Generate → Deploy → Maintain → Upgrade Many Clients

---

## Documentation Index

### [Gap Analysis](./gap-analysis.md)
Systematic analysis of gaps, contradictions, and missing pieces in the original plan. **Read this first** — it identifies 10 critical blockers that must be resolved before implementation.

### Architecture
- [Overview](./architecture/overview.md) — High-level system architecture
- [Rendering Model](./architecture/rendering-model.md) — SSR/SSG/hybrid strategy (GAP-001)
- [Data Flow](./architecture/data-flow.md) — Content/presentation separation
- [Monorepo Structure](./architecture/monorepo.md) — Repository layout

### Backend
- [Overview](./backend/overview.md) — Laravel responsibilities
- [Entities](./backend/entities.md) — Core entities + news domain
- [Media System](./backend/media-system.md) — Media handling
- [API](./backend/api.md) — API endpoints
- [Migrations](./backend/migrations.md) — Migration strategy

### Frontend
- [Overview](./frontend/overview.md) — React stack
- [Component System](./frontend/component-system.md) — Components, registry, schema
- [Theme System](./frontend/theme-system.md) — Themes, tokens, overrides
- [Builder](./frontend/builder.md) — Visual builder

### Generator
- [Overview](./generator/overview.md) — What the generator does
- [Process](./generator/process.md) — Generation pipeline
- [Platform Lock](./generator/platform-lock.md) — Lock file format

### Deployment
- [Overview](./deployment/overview.md) — 3-tier model (shared / VPS / enterprise)
- [Shared Hosting](./deployment/shared-hosting.md) — Tier 1, no-SSH constraints + web flows (GAP-009)
- [VPS/Cloud](./deployment/vps-cloud.md) — Tier 2, SSH + CLI + optional Docker
- [Enterprise](./deployment/enterprise.md) — Tier 3, Kubernetes + Helm + multi-region
- [Update System](./deployment/update-system.md) — Tier-branched update/rollback process

### [Licensing](./licensing/overview.md)
License model and verification protocol

### [Client Customization](./client-customization/overview.md)
4-level customization model

### Development
- [Phases](./development/phases.md) — Development phases (reordered)
- [MVP Scope](./development/mvp.md) — MVP definition
- [Testing](./development/testing.md) — Testing strategy
- [Golden Rules](./development/golden-rules.md) — Engineering principles

### [Decisions](./decisions/README.md)
Architecture Decision Records (ADRs)

---

## Project Status

| Phase | Status |
|-------|--------|
| Gap analysis | ✅ Complete |
 | ADRs | ✅ Accepted (12/12, 2026-09-30) |
| Phase 1 — Core CMS | ⏳ Not started |
| Phase 2 — Component System | ⏳ Not started |
| Phase 3 — Public Website | ⏳ Not started |
| Phase 4 — Visual Builder | ⏳ Not started |
| Phase 5 — Generator | ⏳ Not started |
| Phase 6 — Deployment | ⏳ Not started |

---

## Success Criteria

The MVP is successful when you can do this (§55):

1. Create a project
2. Select Modern theme
3. Add Hero
4. Add News Grid
5. Configure News Grid
6. Reorder sections
7. Change colors
8. See live preview
9. Approve design
10. Generate project
11. Download deployment package
12. Deploy to shared hosting
13. Add real client content
14. Release a component update
15. Upgrade the client site
16. Verify client content/customizations remain intact

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel, MySQL/MariaDB, Sanctum |
| Frontend | React, TypeScript, Vite, Tailwind CSS |
| State | Zustand |
| Testing | PHPUnit/Pest, Vitest, Playwright |
| Deployment | Tier 1: shared hosting (PHP + MySQL + Apache) · Tier 2: VPS/cloud · Tier 3: Kubernetes |

---

*For the original raw plan, see `docs/plan.txt`.*
