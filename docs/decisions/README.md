# Architecture Decision Records (ADRs)

**Status:** 12/12 Accepted (2026-09-30) — all ADRs signed off; Phase 1 gate cleared

---

## What is an ADR?

An Architecture Decision Record (ADR) captures a significant architectural decision, its context, and its consequences. ADRs are immutable once accepted.

---

## ADR Template

```markdown
# ADR-XXXX: [Title]

**Status:** Proposed | Accepted | Deprecated | Superseded
**Date:** YYYY-MM-DD
**Deciders:** [Names]

## Context

[What is the issue that we're seeing that is motivating this decision?]

## Decision

[What is the change that we're proposing and/or doing?]

## Consequences

[What becomes easier or more difficult to do because of this change?]

## Alternatives Considered

[What other options were considered and why were they rejected?]

## References

[Links to related documents, discussions, or issues]
```

---

## ADR Index

| ID | Title | Status | Gap |
|----|-------|--------|-----|
| ADR-0001 | Rendering Model | ✅ Accepted (2026-09-30) | GAP-001 |
| ADR-0002 | Media Transcoding Strategy | ✅ Accepted (2026-09-30) | GAP-003 |
| ADR-0003 | Asset Distribution (CDN) | ✅ Accepted (2026-09-30) | GAP-004 |
| ADR-0004 | Override Resolution | ✅ Accepted (2026-09-30) | GAP-005 |
| ADR-0005 | Comments Scope | ✅ Accepted (2026-09-30) | GAP-008 |
| ADR-0006 | platform.lock Format | ✅ Accepted (2026-09-30) | GAP-006 |
| ADR-0007 | Migration Ordering | ✅ Accepted (2026-09-30) | GAP-007 |
| ADR-0008 | Phase Reordering | ✅ Accepted (2026-09-30) | GAP-002 |
| ADR-0009 | Localization Scope | ✅ Accepted (2026-09-30) | MISSING-001 |
| ADR-0010 | Queue Strategy | ✅ Accepted (2026-09-30) | CONTRADICTION-002 |
| ADR-0011 | No-SSH Deployment | ✅ Accepted (2026-09-30) | GAP-009 |
| ADR-0012 | Deployment Tiers & Capability Layer | ✅ Accepted (2026-09-30) | GAP-010 |

---

## ADR-0001: Rendering Model

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-001

**Decision:** Hybrid rendering model — Blade shells for page layout and SEO-critical HTML, React islands for interactive elements, build-time SSG for content pages. **No request-time SSR** — Node.js is forbidden on production, so the plan's "SSR/SSG" is read as prerender-at-build.

**See:** [Rendering Model](../architecture/rendering-model.md)

---

## ADR-0002: Media Transcoding Strategy

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-003

**Decision (accepted 2026-09-30):** Synchronous processing for MVP; cron-based queue for bulk operations

**See:** [Media System](../backend/media-system.md)

---

## ADR-0003: Asset Distribution (CDN)

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-004

**Decision (accepted 2026-09-30):** Platform hosts prebuilt asset bundles on CDN; update system downloads and swaps atomically

**See:** [Update System](../deployment/update-system.md)

---

## ADR-0004: Override Resolution

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-005

**Decision (accepted 2026-09-30):** 3-layer resolution (client override → theme default → platform registry); 3-way merge on update

**See:** [Theme System](../frontend/theme-system.md)

---

## ADR-0005: Comments Scope

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-008

**Decision:** Comments are **out of MVP**. The `comments` flag leaves MVP scope; the Comment entity, moderation workflow (pending → approved → rejected), spam filtering, and rate limiting are designed as a Phase 7+ feature.

**See:** [MVP Scope](../development/mvp.md)

---

## ADR-0006: platform.lock Format

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-006

**Context:** The plan's lock file (§29) has no `generator` field (even though §30 versions the generator), no `core` version distinct from `platform`, and tracks none of: client overrides, migration version, asset bundle checksum, feature flags. An incomplete lock means an incomplete update capability — the file is supposed to be the source of truth for "what is this client running."

**Decision (accepted 2026-09-30):**
1. Single JSON file `platform.lock` at site root — **platform-controlled**, clients never edit it
2. Complete field set: `platform`, `generator`, `core`, `deployment_type`, `theme`, `components`, `client_overrides`, `migration_version`, `asset_bundle` (SHA-256), `features`
3. Three version axes kept separate — `platform` (central), `core` (client engine), `generator` — so each can ship independently
4. Lifecycle: written at generation → updated only after a successful update → restored from backup on rollback
5. Format evolution is **additive-only**; no field removals/renames without a platform major bump

**Consequences:**
- ✅ Update system can run exact compatibility checks and select the correct tier-specific package
- ✅ Asset integrity + rollback verification via checksum
- ✅ Support can diagnose any client from one file
- ⚠️ File can drift if someone hand-edits files → update flow must re-validate, not trust blindly
- ⚠️ `migration_version` must be written only *after* migrations commit

**Alternatives considered:**
- *Server-side registry only* — rejected: the site must be self-describing for offline diagnosis, support, and restore-from-backup scenarios
- *Split lockfiles per domain* — rejected: one source of truth is simpler; split files drift apart

**See:** [Platform Lock](../generator/platform-lock.md), [Generation Process](../generator/process.md), [Update System](../deployment/update-system.md), [Gap Analysis](../gap-analysis.md) — GAP-006

---

## ADR-0007: Migration Ordering

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-007

**Context:** The plan splits migrations into platform-controlled and client-controlled, but defines no ordering rule for interleaved migrations. A client migration sitting between two platform migrations creates ambiguity; a platform migration touching a client table creates corruption risk on upgrade — the exact failure the update system must prevent.

**Decision (accepted 2026-09-30):**
1. Keep Laravel's **timestamped migration names** — implicit chronological ordering
2. Two directories: `database/migrations/platform/` and `database/migrations/client/`
3. Runner order: **all platform migrations first, then client migrations**
4. Ownership rule: platform never modifies tables it doesn't own (enforced by convention + code review); unavoidable modification → guarded `Schema::table()` with `hasColumn()` checks
5. Platform migrations are **append-only and idempotent** — safe to re-run after a partial failure
6. Same ordering on every tier — web runner (T1) / `artisan migrate` (T2) / K8s Job (T3) all execute `platform/` then `client/`
7. `platform.lock → migration_version` records the last applied platform migration

**Consequences:**
- ✅ Deterministic upgrades — no interleaving ambiguity
- ✅ Idempotence makes retries and web-runner crash recovery safe
- ⚠️ Ownership is convention-enforced, not schema-enforced — needs review checklist
- ⚠️ Long platform migrations must chunk under Tier 1 `max_execution_time` (web runner)

**Alternatives considered:**
- *Single merged namespace with `platform_` prefix* — rejected: ordering ambiguity returns; two directories make ownership mechanically visible
- *Lockstep schema versioning (client schema = platform schema version)* — rejected: forces every platform release to re-provision client schema, killing independent client migrations

**See:** [Backend Migrations](../backend/migrations.md), [Shared Hosting web runner](../deployment/shared-hosting.md), [Update System](../deployment/update-system.md), [Gap Analysis](../gap-analysis.md) — GAP-007

---

## ADR-0008: Phase Reordering

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-002

**Context:** The original plan (§48) builds the full React public website (old Phase 2) *before* the component system and theme system (old Phases 3+4), while Golden Rule 19 states "build reusable components before building many themes." The order guarantees Phase 2's website gets rewritten once the component system lands.

**Decision (accepted 2026-09-30):**
1. Adopt the corrected order (see [Development Phases](../development/phases.md)):
   1 Core CMS → 2 Component System + Renderer + Modern Theme → 3 Public Website → 4 Visual Builder → 5 Generator → 6 Deployment (Tier 1) → 7 Update System → 8 Licensing → 9 Rust CLI → 10 VPS/Cloud + Enterprise Tiers
2. **MVP boundary:** ends at Phase 6; Phases 7–10 labeled Post-MVP
3. Capability layer lands inside Phase 1 (ADR-0012) so Phase 10 needs no app rework

**Consequences:**
- ✅ Component + theme architecture proven before any page is built on it
- ✅ Explicit MVP boundary blocks scope creep (contradiction with "do not build everything" resolved)
- ✅ Page components in Phase 3 become mere compositions of proven components
- ⚠️ Visible website arrives later than the original plan promised — demos/marketing must wait for Phase 3

**Alternatives considered:**
- *Keep original order* — rejected: guaranteed rewrite of the public site (GAP-002 impact)
- *Parallel tracks (website skeleton alongside components)* — rejected: the skeleton would be built against contracts that don't exist yet, i.e. the same rewrite with extra steps

**See:** [Development Phases](../development/phases.md), [Golden Rules](../development/golden-rules.md), [Gap Analysis](../gap-analysis.md) — GAP-002, CONTRADICTION-003

---

## ADR-0009: Localization Scope

**Status:** ✅ Accepted — 2026-09-30 (decision: foundation-only)
**Gap:** MISSING-001

**Context:** The plan has no i18n anywhere. Bengali-language news sites are plausible from day one (developer/market context), and retrofitting i18n after content, admin, components, and URLs exist is exponentially expensive. But full i18n (translations + RTL + locale-aware search/SEO) roughly doubles MVP QA surface.

**Decision (accepted 2026-09-30) — "localize the foundation, defer the UI":**
1. MVP ships **single-locale (English)**, but the foundation is locale-ready from migration #1:
   - `locale` column on posts, categories, tags (default `en`, indexed)
   - Zero hardcoded strings rule — admin UI via Laravel `__()`, components via translation catalogs
   - React side wraps all output through the i18n layer from day one (single catalog initially)
2. Router reserves an optional locale segment (`/en/...`, `/bn/...`) so URLs don't change when locales ship
3. Full Bengali locale (catalog, RTL layout, locale-aware SEO/sitemaps) = **post-MVP (Phase 7+)**
4. Enforced by lint: a hardcoded-string check in CI for UI code

**Consequences:**
- ✅ Retrofit cost shrinks to *translating catalogs + RTL styling* — schema and routing already done
- ✅ MVP scope stays small
- ⚠️ Discipline tax from day one (lint rule + review)
- ⚠️ RTL testing deferred — will surface layout bugs later

**Alternatives considered:**
- *Full i18n in MVP* — rejected: doubles QA and delays every MVP date (unless product decides Bengali is launch-critical — **this is the open decision**)
- *Do nothing now* — rejected: adding `locale` to a live content model + changing URL structure is the exponential path

**Decision recorded (2026-09-30):** foundation-only — MVP ships English on a locale-ready foundation (`locale` column, translation layers, reserved URL segment); full Bengali/RTL is explicitly post-MVP (Phase 7+). If product later pulls full i18n into MVP, **supersede this ADR** — do not amend it.

**See:** [Gap Analysis](../gap-analysis.md) — MISSING-001, [Backend Entities](../backend/entities.md)

---

## ADR-0010: Queue Strategy

**Status:** ✅ Accepted — 2026-09-30
**Gap:** CONTRADICTION-002 (refs GAP-003)

**Context:** The plan forbids "long-running workers" (§2 — forced by shared hosting) yet requires media variant generation and bulk operations (§8) and casually mentions "Laravel Queue where necessary" (§65). A queue without a worker doesn't run; a worker can't run on Tier 1. Both statements can't hold without an execution model.

**Decision (accepted 2026-09-30) — capability-driven, three modes:**
1. **Tier 1 (`has_queue=false`):**
   - Single-item work runs **synchronously**: `spatie/laravel-medialibrary` + `spatie/image` for image variants; FFmpeg invoked inline on upload for video (document a file-size ceiling)
   - Bulk work (variant regeneration, batch imports) runs via cron **`queue:work --once`** — one job per cron tick, no long-running process
   - Queue driver: `database` — **no Redis required for MVP**
2. **Tier 2/3 (`has_queue=true`):** Redis queue + Supervisor/Horizon (T2) or K8s Deployment (T3), long-running workers permitted
3. Jobs are written **once** and dispatched behind a capability check — tiers differ in *execution*, never in job classes
4. Media specifically: images always sync (fast enough); video sync on T1, queued on T2/3

**Consequences:**
- ✅ Contradiction resolved without violating either constraint (no workers on T1, real queues where workers exist)
- ✅ MVP ships with zero infrastructure requirements beyond PHP + MySQL
- ✅ Same job code migrates from cron-tick to Horizon by flipping a capability
- ⚠️ T1 bulk throughput is bounded by cron cadence (document: "batch of 200 images ≈ N cron ticks")
- ⚠️ Sync video caps upload size on T1 — explicit limit + friendly error

**Alternatives considered:**
- *Always async* — rejected: impossible on Tier 1 (no workers)
- *Always sync* — rejected: bulk operations would block HTTP requests on Tier 2/3
- *Separate job classes per tier* — rejected: doubles maintenance; capability gate gives the same variance with one codepath

**See:** [Media System](../backend/media-system.md), [Testing — tier matrix](../development/testing.md), [Platform Lock — capabilities](../generator/platform-lock.md), [Gap Analysis](../gap-analysis.md) — GAP-003, CONTRADICTION-002

---

## ADR-0011: No-SSH Deployment

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-009

**Context:** Clients run shared hosting with **no SSH access**. Every server-side operation the plan assumes (`artisan key:generate`, `artisan migrate`, `composer install`, `npm run build`, `crontab`) is unavailable.

**Decision (accepted 2026-09-30):**
1. **Web installer** (WordPress-style wizard) generates `.env`, `APP_KEY`, runs migrations, seeds, writes `platform.lock` — all in-browser
2. **Web migration runner** — `POST /admin/migrations/run` executes migrations without artisan (admin auth + CSRF + one-time lock; chunked progress for long runs)
3. **Packages ship complete** — `vendor/` included, assets prebuilt, config pre-cached; no build or install step on server
4. **Cron without shell** — cPanel Cron GUI, external HTTP cron service (`GET /api/cron/run?key=...`), or in-app fallback button
5. **Web update panel** — one-click backup → download → checksum → file swap → web migrations → health check → rollback

**Consequences:**
- ✅ Zero-shell install/update/migrate works on any Apache+PHP+MySQL host
- ✅ Same codebase serves all tiers (capability layer decides paths)
- ⚠️ Migration runner must be hardened (auth, locking, timeouts, PHP `max_execution_time` workarounds)
- ⚠️ Update ZIPs are larger (ship `vendor/`)
- ❌ Nginx-only hosts out of scope for Tier 1 (route to Tier 2)

**Alternatives considered:**
- *Require SSH* — rejected: violates client constraint
- *FTP + instruction docs (manual CLI steps)* — rejected: error-prone, support-heavy, not a product
- *Remote SSH agent from platform* — rejected: hosts rarely allow key-based access; fragile

**See:** [Shared Hosting](../deployment/shared-hosting.md), [Update System](../deployment/update-system.md), [Migrations](../backend/migrations.md)

---

## ADR-0012: Deployment Tiers & Capability Layer

**Status:** ✅ Accepted — 2026-09-30
**Gap:** GAP-010

**Context:** The platform must serve three client segments — shared hosting (no SSH), VPS/cloud (full SSH toolchain), and enterprise (K8s/HA/SLAs) — without forking the codebase or letting Tier 1 assumptions cap the product.

**Decision (accepted 2026-09-30):**
1. **Three tiers, one architecture** — `deployment_type` in `platform.lock`: `shared` | `vps` | `enterprise`; written at generation, immutable per site
2. **Tier-specific artifacts** —
   - `shared`: ZIP with `vendor/` + prebuilt assets + web installer
   - `vps`: ZIP + `deploy/provision.sh` + `deploy/update.sh` (+ optional `docker/`)
   - `enterprise`: multi-stage Docker image + Helm chart (migration Job, HPA, CronJob)
3. **Capability layer over tier branches** — `config/capabilities.php` derives `has_queue`, `has_redis`, `has_ssh_tooling`, `has_web_migrations`, `has_workers`, ... Runtime code checks capabilities, never `deployment_type === 'shared'`
4. **Tier-branched delivery, shared semantics** — update flow is always backup → compat → apply → migrate → health → lock; only trigger/execution differ (web panel | CLI | image rollout)
5. **Generator validation** — reject configs needing capabilities absent from the chosen tier (e.g. Redis queue on `shared`)

**Consequences:**
- ✅ Enterprise/VPS clients get queue, Redis, HA, zero-downtime rollouts without changing app code
- ✅ MVP (Tier 1) ships without painting the architecture into a corner
- ✅ New tier (e.g. `hybrid`) becomes additive — new capability values, not code rewrites
- ⚠️ Testing matrix ×3 — needs a Tier 1 fixture (no SSH/Node/Redis) in CI + real-host smoke test
- ⚠️ Release engineering: per-tier packages, checksums, rollback paths per tier

**Alternatives considered:**
- *Single lowest-common-denominator package* — rejected: caps product at shared hosting, wastes VPS/enterprise potential
- *Tier checks scattered in code (`if ($type === ...)`)* — rejected: untestable, unbounded branching; capability queries stay local and mockable
- *Separate codebases per tier* — rejected: triple maintenance, divergence guaranteed

**Sequencing rule:** build the capability layer in Phase 1, ship Tier 1, add Tier 2/3 packaging later **without touching app logic**. Retrofitting after the fact is the highest-cost path (see GAP-010 impact).

**See:** [Deployment Overview](../deployment/overview.md), [Platform Lock](../generator/platform-lock.md), [Generation Process](../generator/process.md), [Gap Analysis](../gap-analysis.md) — GAP-010

---

## Related Documents

- [Gap Analysis](../gap-analysis.md)
- [Architecture Overview](../architecture/overview.md)
- [Development Phases](../development/phases.md)
