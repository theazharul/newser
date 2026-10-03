# Gap Analysis — News Site Generation Platform

**Source:** `docs/plan.txt` (55 sections, 1902 lines)
**Date:** 2026-09-30
**Status:** Pre-implementation — no code exists yet

---

## Executive Summary

| Severity | Count | Description |
|----------|-------|-------------|
| 🔴 CRITICAL | 10 | Blocks all implementation — must resolve before writing code |
| 🟠 CONTRADICTION | 5 | Plan contradicts itself — needs adjudication |
| 🟡 MISSING | 11 | Entirely absent topics — must be designed |
| 🔵 UNDEFINED | 14 | Mentioned but never specified — needs design decisions |
| ⚪ INCOMPLETE | 7 | Sketched but not buildable as-is |

**Recommendation:** Resolve all 🔴 CRITICAL items via Architecture Decision Records (ADRs) before any implementation begins. The rendering model (GAP-001) is the highest-leverage decision — it constrains everything downstream.

---

## 1. Critical Blockers (CRITICAL)

### GAP-001: Rendering Model Undefined
**Category:** Architecture
**Severity:** 🔴 CRITICAL
**References:** §2 (lines 69-71), §35 (lines 1089-1108), §41 (lines 1259-1268)

**Problem:** The plan demands "SSR/SSG where appropriate" (§2) and "Do not turn the public news website into an unnecessarily heavy SPA" (§35), but simultaneously forbids "Node.js runtime on production" (§2, §41). These constraints are only compatible if "SSR" means **build-time prerender**, not request-time render. The plan never names a framework (Inertia? Livewire? Blade + React islands?) and never decides the rendering strategy.

**Impact:** Every downstream decision depends on this:
- Component contracts (server-rendered vs. client-hydrated)
- SEO implementation (meta tags, structured data, sitemap)
- Generator build pipeline
- Preview renderer architecture
- Caching strategy
- Deployment artifact format

**Proposed Resolution:** Adopt a **hybrid rendering model**:
1. Laravel + Blade templates for page shells, layouts, header/footer (SEO-critical, fast TTFB)
2. React islands for interactive components (search, comments, newsletter signup, ad rotation)
3. Build-time SSG for article/category pages where content is known at build time
4. Lazy-hydration for below-the-fold interactive elements
5. `platform-renderer` package encapsulates the rendering strategy so preview and production use identical code paths

**Owner:** Architecture team
**Effort:** 2-3 days design + ADR

---

### GAP-002: Phase Order Contradicts Golden Rules
**Category:** Process
**Severity:** 🔴 CRITICAL
**References:** §48 (lines 1443-1635), §51 Golden Rule 19 (line 1777)

**Problem:** Phase 2 builds a full React public website (homepage, article, category, search, author pages) *before* Phase 3 builds the component system and Phase 4 builds the theme system. Golden Rule 19 explicitly states "Build reusable components before building many themes." The current order guarantees Phase 2's website will be rewritten in Phase 3.

**Impact:** Wasted effort, architectural churn, potential abandonment of Phase 2 work.

**Proposed Resolution:** Reorder phases:
1. **Phase 1:** Core CMS (Laravel + auth + posts + categories + media) — *unchanged*
2. **Phase 2:** Component system + renderer + one theme (Modern) — *was Phase 3+4*
3. **Phase 3:** Public website using the component system — *was Phase 2*
4. **Phase 4:** Visual builder — *was Phase 5*
5. **Phase 5:** Generator — *was Phase 6*
6. **Phase 6:** Deployment — Tier 1 / no-SSH (was 7)
7. **Phase 7-9:** Updates, licensing, Rust CLI — *post-MVP* (was 8-10)
8. **Phase 10:** VPS/Cloud + Enterprise tiers *(new — GAP-010)* — *post-MVP*

This ensures the component system and theme architecture are proven before building pages on top of them.

**Owner:** Tech lead
**Effort:** 1 day replanning

---

### GAP-003: Media Transcoding Has No Worker
**Category:** Infrastructure
**Severity:** 🔴 CRITICAL
**References:** §8 (lines 300-340), §2 (lines 90-98), §41 (lines 1259-1268)

**Problem:** §8 requires WebP/AVIF image variants and video support. §2 forbids "long-running workers." §41 forbids Node.js on production. Laravel queues require a worker process (`php artisan queue:listen`). Something must transcode images and videos. The plan provides no answer.

**Impact:** Media system cannot be built as specified. Image variants cannot be generated. Video cannot be processed.

**Proposed Resolution:**
1. Use Laravel's `spatie/laravel-medialibrary` + `spatie/image` for synchronous image conversion (no worker needed for images)
2. For video: use a server-side FFmpeg binary invoked synchronously on upload (acceptable for MVP with small files)
3. Queue only for bulk operations (regenerating all variants, batch imports)
4. Shared hosting: use `php artisan queue:work --once` via cron as a poor-man's worker (processes one job per cron tick)
5. Document that Redis/Elasticsearch are NOT required — database queue driver is sufficient

**Owner:** Backend team
**Effort:** 2 days research + implementation plan

---

### GAP-004: Update System Cannot Ship React Code to Node-less Hosts
**Category:** Architecture
**Severity:** 🔴 CRITICAL
**References:** §31 (lines 993-1020), §41 (lines 1259-1268)

**Problem:** §31 says "Update packages → Build React assets → Run tests." But §41 forbids Node/npm on production. A component update that changes React source means the platform must ship prebuilt, compiled asset bundles per platform version. The update system must swap them atomically. This hosting/publishing requirement is entirely unplanned.

**Impact:** The update system — the core value proposition of the platform — cannot work as designed.

**Proposed Resolution:**
1. Platform hosts a **CDN or artifact repository** containing prebuilt asset bundles per (platform_version, component_version) pair
2. Generated sites ship with a `platform-assets/` directory containing compiled JS/CSS
3. Update process:
   - Download new asset bundle from platform CDN
   - Verify checksum
   - Atomic swap: move old assets to backup, extract new assets
   - Update `platform.lock`
   - Run health check
   - Rollback on failure
4. Platform build pipeline: CI builds all component combinations and publishes to CDN
5. Cost: minimal (static file hosting); can use Cloudflare R2/S3

**Owner:** Platform team
**Effort:** 3-4 days design + implementation

---

### GAP-005: Theme/Component Override Resolution Undefined
**Category:** Architecture
**Severity:** 🔴 CRITICAL
**References:** §18 (lines 593-622), §19 (lines 625-664)

**Problem:** §18 says clients override individual components (e.g., a custom `Header`) without copying the entire theme. But there is no defined:
- Precedence order (client override vs. theme default vs. component registry)
- Merge semantics (partial component override — what happens to unspecified props?)
- Version tracking for client-authored components
- Conflict resolution when platform updates a component the client has overridden

**Impact:** The override system is the mechanism that makes multi-tenant updates safe. Without it, updates will destroy client customizations.

**Proposed Resolution:**
1. Define a **3-layer resolution order**:
   - Layer 1: Client override (highest priority)
   - Layer 2: Theme default component
   - Layer 3: Platform component registry (lowest priority)
2. Client overrides are stored as **patch files** (not full copies), keyed by `(component_name, base_version)`
3. Override format: JSON Patch (RFC 6902) or a simple `{ "props": { ... } }` partial merge
4. When platform updates a component:
   - If client has no override → apply update cleanly
   - If client has override → run 3-way merge (base_version + client_patch + new_version)
   - Conflict → flag for manual resolution, keep client override
5. `platform.lock` tracks client overrides separately from platform components

**Owner:** Architecture team
**Effort:** 3 days design + prototype

---

### GAP-006: platform.lock Is Incomplete
**Category:** Data Model
**Severity:** 🔴 CRITICAL
**References:** §29 (lines 934-960), §30 (lines 963-990)

**Problem:** §30 versions the generator, but §29's lock file has no generator field. There is no distinct `core` version field separate from `platform`. The lock also doesn't track:
- Client overrides (see GAP-005)
- Database migration version
- Asset bundle checksum
- Feature flags enabled for this client

**Impact:** The lock file is the source of truth for "what is this client running." Incomplete lock = incomplete update capability.

**Proposed Resolution:** Extend `platform.lock`:
```json
{
  "platform": "3.2.1",
  "generator": "1.4.0",
  "core": "3.2.1",
  "theme": { "name": "modern", "version": "2.4.0" },
  "components": {
    "hero": "2.0.0",
    "news-grid": "3.1.0"
  },
  "client_overrides": {
    "header": { "base_version": "1.2.0", "override_id": "abc123" }
  },
  "migration_version": "2026_09_01_000000",
  "asset_bundle": "sha256:abc123...",
  "features": { "comments": true, "video": true }
}
```

**Owner:** Backend team
**Effort:** 1 day

---

### GAP-007: Migration Ordering Across Platform/Client Boundary
**Category:** Data Model
**Severity:** 🔴 CRITICAL
**References:** §33 (lines 1044-1062)

**Problem:** §33 splits migrations into platform-controlled and client-controlled but defines no ordering scheme for interleaved migrations. When a client migration sits between two platform migrations, which runs first? What happens when a platform migration modifies a table the client migration also touches?

**Impact:** Database corruption on upgrade — the exact failure mode the update system is supposed to prevent.

**Proposed Resolution:**
1. Use **timestamped migration names** (Laravel default) — ordering is implicit
2. Platform migrations use a `platform/` subdirectory: `database/migrations/platform/`
3. Client migrations use `database/migrations/client/`
4. Migration runner: platform migrations first, then client migrations
5. Platform never modifies tables it doesn't own (enforced by convention + code review)
6. If platform needs to modify a client table → new platform migration with `Schema::table()` guarded by `hasColumn()` checks
7. Document: "Platform migrations are append-only and idempotent"

**Owner:** Backend team
**Effort:** 1 day design + convention doc

---

### GAP-008: Comments Feature Orphaned
**Category:** Feature
**Severity:** 🔴 CRITICAL
**References:** §26 (line 879), §5, §6, §48

**Problem:** Comments appear as a feature flag in §26 (`"comments": true`) but are absent from:
- §5 (Laravel responsibilities)
- §6 (core entities — no `Comment` model)
- §48 (development phases — no comment system built)
- §9 (component system — no comment component)
- §46 (testing — no comment tests)

**Impact:** Comments are either an unplanned feature or a phantom reference. Either way, the feature flag is misleading.

**Proposed Resolution:**
1. **MVP decision:** Remove comments from MVP scope entirely
2. Remove `"comments": true` from §26 feature flags
3. If comments are needed later → design as Phase 2+ feature with:
   - `Comment` entity (id, post_id, user_id, body, status, created_at)
   - Moderation workflow (pending → approved → rejected)
   - Spam filtering (Akismet or similar)
   - Rate limiting
   - Comment component (React)
   - API endpoints (GET /posts/{id}/comments, POST /posts/{id}/comments)

**Owner:** Product team
**Effort:** 30 minutes (remove from MVP) or 5 days (design for Phase 2)

---

### GAP-009: No SSH Access on Client Shared Hosting
**Category:** Infrastructure
**Severity:** 🔴 CRITICAL
**References:** §41 (lines 1259-1268), user constraint

**Problem:** Clients will use shared hosting **without SSH access**. This means:
- No `php artisan` CLI commands (no `key:generate`, `migrate`, `config:cache`, `storage:link`)
- No `composer install` on server
- No `npm run build` on server
- No `crontab` access
- No SSH for any server-side operations

**Impact:** The entire deployment, update, and migration architecture must be redesigned:
- **Installation:** Must be web-based (like WordPress installer) — no CLI steps
- **Migrations:** Must run via web-based migration runner, not `php artisan migrate`
- **Updates:** Must be pure file replacement — no CLI commands
- **Cron jobs:** Must use cPanel cron or external cron service, not `crontab`
- **Configuration:** Must auto-generate `.env` on first web request
- **Assets:** Must ship pre-built — no build step on server

**Proposed Resolution:**
1. **Web-based installer:** First web request triggers setup wizard (like WordPress)
   - Auto-generate `APP_KEY`
   - Auto-create `.env` from `.env.example` + user input
   - Auto-run migrations via web
   - Auto-create storage directories
   - Auto-set permissions
2. **Web-based migration runner:** `POST /admin/migrations/run` triggers pending migrations (admin auth + CSRF + one-time lock)
3. **Web-based update system:** Upload ZIP → extract → run migrations via web → done
4. **Cron via cPanel:** Provide cron setup instructions for cPanel UI
5. **Ship with `vendor/`:** Include `composer dependencies` in the ZIP — no `composer install` needed
6. **Ship pre-built assets:** Include compiled JS/CSS in the ZIP — no `npm run build` needed

**Owner:** Platform team
**Effort:** 3-4 days design + implementation

---

### GAP-010: Deployment Tier Differentiation Undefined
**Category:** Infrastructure
**Severity:** 🔴 CRITICAL
**References:** §41-43 (deployment modes table), user constraint ("some client may have VPS/cloud — must provide enterprise solution")

**Problem:** The plan lists three deployment modes (Shared Hosting / VPS / Enterprise) in a single table but designs **only one** delivery path — the shared-hosting ZIP. There is no specification for how the three tiers differ in:

- **Packaging:** one ZIP for all tiers vs. tier-specific artifacts (ZIP with `vendor/`+installer vs. ZIP with deploy scripts vs. Docker image + Helm chart)
- **Install:** web installer (T1) vs. CLI provisioning (T2) vs. Helm/GitOps (T3)
- **Migrations:** web runner vs. `artisan migrate` vs. K8s Job
- **Updates:** web panel vs. CLI vs. image rollout
- **Cron/workers:** HTTP cron vs. Supervisor+Horizon vs. K8s CronJob/Deployment
- **Capability detection:** no mechanism for runtime code to know what the environment supports

**Impact:** If implementation proceeds assuming the Tier 1 (shared/no-SSH) path only:
- VPS/Enterprise clients get a degraded product (no queue, no Redis, no HA)
- Retrofitting tiers later forces `if shared / else` branches across every subsystem (packaging, migrations, updates, cron) — the exact "highest cost if deferred" risk
- Update API may hand a `vps` package to a `shared` site (missing `vendor/`, missing installer) or vice versa
- The generator has no field to target a tier, so `platform.lock` cannot describe what a site is running on

**Proposed Resolution:**
1. **`deployment_type` field in `platform.lock`** (`shared` | `vps` | `enterprise`), written at generation time, immutable for the site's lifetime
2. **Capability layer over tier checks** — `config/capabilities.php` derives `has_queue`, `has_redis`, `has_ssh_tooling`, `has_web_migrations`, etc.; runtime code asks "can I do X?" not "which tier am I?"
3. **Tier-specific packaging** in the generator (ZIP+`vendor/`+installer | ZIP+deploy scripts | image+Helm)
4. **Tier-branched update system** — shared semantics (backup → compat → apply → migrate → health), different trigger/execution per tier
5. **Separate deployment docs per tier** — `deployment/shared-hosting.md` (no-SSH), `vps-cloud.md`, `enterprise.md`
6. **Validation rule:** generator rejects configs requiring capabilities absent from the chosen tier (e.g. Redis queue on `shared`)

**Documents updated:** `deployment/overview.md`, `deployment/shared-hosting.md`, `deployment/vps-cloud.md` (new), `deployment/enterprise.md` (new), `deployment/update-system.md`, `generator/process.md`, `generator/platform-lock.md`

**Owner:** Architecture team
**Effort:** 2-3 days design + ADR (ADR-0012); packaging/CI work tracked separately in phases

---

## 2. Internal Contradictions (CONTRADICTION)

### CONTRADICTION-001: SSR/SSG vs. No Node on Production
**References:** §2 (line 70), §41 (lines 1259-1268)
**Detail:** "Prefer SSR/SSG" implies server-side rendering, but "no Node.js on production" means no runtime SSR. Only build-time prerender is compatible.
**Resolution:** See GAP-001. Adopt hybrid model with build-time prerender.

### CONTRADICTION-002: Queue Workers vs. No Long-Running Processes
**References:** §2 (line 97), §8 (media variants), §65 (Laravel Queue)
**Detail:** §2 forbids "long-running workers" but §65 mentions "Laravel Queue where necessary" and §8 requires media variant generation (async work).
**Resolution:** See GAP-003. Use synchronous processing for MVP; cron-based `queue:work --one` for bulk ops.

### CONTRADICTION-003: "Do Not Build Everything" vs. 10-Phase Plan
**References:** §49 (MVP), §48 (10 phases), §50 (do not build)
**Detail:** §49 correctly narrows MVP, but §48 still lists all 10 phases including Rust CLI (Phase 10). The phases should be labeled "post-MVP" to avoid scope creep.
**Resolution:** Label Phases 7-10 as "Post-MVP" in the restructured docs. Add explicit "MVP ends at Phase 6" boundary.

### CONTRADICTION-004: Theme Independence vs. Component Variants
**References:** §15 (lines 509-534), §16 (lines 537-562)
**Detail:** §15 says themes contain "component variants" but §16 says "Themes must NOT contain core business logic." A component variant is business logic. Where does it live?
**Resolution:** Define "component variant" as a **presentation-only override** (CSS + template structure, no data fetching or business logic). Data fetching stays in the component's data provider; the theme only controls how it looks.

### CONTRADICTION-005: Platform-Controlled Core vs. Client Site Has Its Own Admin
**References:** §19 (line 663), §28 (lines 915-930)
**Detail:** §19 says "Clients NEVER directly modify Level 4 (Laravel/platform engine)." But §28 shows the generated client site has its own `Admin` section. Is the client admin a Level 4 modification?
**Resolution:** Clarify: the client site's Admin is a **Level 1 (Content) interface** — it manages posts, categories, media, etc. It does NOT modify platform code. The distinction is: "Admin UI" (allowed, Level 1) vs. "Core engine code" (forbidden, Level 4).

---

## 3. Missing Subsystems (MISSING)

### MISSING-001: Localization / i18n
**Severity:** 🟡 MISSING
**Impact:** If Bengali-language news sites are in scope (likely given the dev's Bangladesh timezone), the entire content model, admin UI, and public site must support multi-language from day one. Retrofitting i18n is exponentially more expensive.
**Proposed Resolution:**
- Add `locale` field to posts, categories, tags
- Use Laravel's built-in localization for admin UI
- React-i18next for public site
- URL structure: `/en/...` and `/bn/...` prefixes
- **Decision needed:** Is multi-language in MVP scope?

### MISSING-002: Accessibility (a11y)
**Severity:** 🟡 MISSING
**Impact:** Public news sites must meet WCAG 2.1 AA. Retrofitting a11y is expensive and often fails.
**Proposed Resolution:**
- Add a11y requirements to component contracts (ARIA labels, keyboard nav, contrast)
- Add automated a11y testing (axe-core) to CI
- Document a11y checklist in `docs/development/golden-rules.md`

### MISSING-003: Comment Moderation Workflow
**Severity:** 🟡 MISSING
**Impact:** If comments are enabled (§26), there is no moderation system.
**Proposed Resolution:** See GAP-008. Remove from MVP or design fully.

### MISSING-004: Analytics & Consent
**Severity:** 🡡 MISSING
**Impact:** News sites need analytics. GDPR/privacy laws require consent management.
**Proposed Resolution:**
- MVP: Google Analytics 4 with consent mode
- Self-hosted analytics (Plausible/Matomo) as alternative
- Consent banner component
- Document in `docs/development/golden-rules.md`

### MISSING-005: Error Monitoring
**Severity:** 🟡 MISSING
**Impact:** No visibility into production failures.
**Proposed Resolution:**
- Sentry (free tier) for Laravel + React
- Health check endpoint (`/api/health`)
- Alerting via email/Slack

### MISSING-006: Backup Storage Location
**Severity:** 🟡 MISSING
**References:** §44 (lines 1330-1340)
**Detail:** §44 mandates backups before updates but never says where backups live. Shared hosting has no object storage.
**Proposed Resolution:**
- Backup to platform's cloud storage (S3/R2) via API
- Client pays for storage (or platform bundles it)
- Retention: last 5 backups
- Test restore monthly

### MISSING-007: API Versioning Strategy
**Severity:** 🟡 MISSING
**Impact:** Client sites communicate with the platform for updates, license verification, and asset downloads. Without versioning, breaking changes break all clients.
**Proposed Resolution:**
- URL prefix: `/api/v1/...`
- Deprecation policy: N-1 support
- Version in `platform.lock`

### MISSING-008: CI/Branch Strategy
**Severity:** 🟡 MISSING
**Impact:** No automated testing or deployment pipeline.
**Proposed Resolution:**
- GitHub Actions: run tests on PR, build on merge to `main`
- Branch strategy: `main` (stable), `develop` (integration), `feat/*` (features)
- Monorepo: changeset-based versioning for packages

### MISSING-009: Database Indexing Strategy
**Severity:** 🟡 MISSING
**Impact:** News sites are read-heavy. Without proper indexing, performance degrades as content grows.
**Proposed Resolution:**
- Index: `posts(status, published_at)`, `posts(category_id, status)`, `posts(slug)`
- Full-text index on `posts(title, excerpt, body)` for search
- Document in `docs/backend/entities.md`

### MISSING-010: Caching Strategy
**Severity:** 🟡 MISSING
**Impact:** High traffic without caching = slow pages + high server load.
**Proposed Resolution:**
- Laravel page cache for public pages (file-based, no Redis)
- Cache invalidation on publish/update
- HTTP cache headers (ETag, Cache-Control)
- Document in `docs/deployment/shared-hosting.md`

### MISSING-011: Rate Limiting Specifics
**Severity:** 🟡 MISSING
**References:** §45 (line 1354)
**Detail:** §45 mentions "rate limiting" but no specifics.
**Proposed Resolution:**
- Laravel's built-in rate limiter (file-based)
- API: 60 req/min per IP
- Login: 5 attempts/min per IP
- Search: 30 req/min per IP

---

## 4. Undefined Mechanisms (UNDEFINED)

### UNDEFINED-001: Component Data Provider Contract
**References:** §14 (lines 476-505)
**Detail:** §14 says components receive `{ items: [...] }` but never specifies the exact shape, error handling, or loading states.
**Proposed Resolution:** Define a `DataProvider<T>` interface:
```typescript
interface DataProvider<T> {
  fetch(config: ComponentConfig): Promise<{
    data: T[];
    error: string | null;
    loading: boolean;
  }>;
}
```

### UNDEFINED-002: Component Schema Validation
**References:** §12 (lines 421-450)
**Detail:** §12 shows a JSON schema sketch but no validation mechanism.
**Proposed Resolution:**
- Use Zod for schema validation
- Schema registry: `packages/news-components/registry.ts`
- Validation on builder save + renderer load

### UNDEFINED-003: Theme Token Resolution
**References:** §17 (lines 564-590)
**Detail:** §17 says "Client configuration modifies tokens" but doesn't define how tokens cascade (global → section → component).
**Proposed Resolution:**
- CSS custom properties for tokens
- Resolution order: component prop → section token → theme token → client override
- TypeScript type for tokens (compile-time safety)

### UNDEFINED-004: Preview Renderer Data Flow
**References:** §22-24 (lines 738-829)
**Detail:** The preview renderer reads page configuration and resolves components, but the data flow for demo content is undefined.
**Proposed Resolution:**
- Demo content stored in `packages/news-renderer/demo-data/`
- Seeder generates demo posts, categories, authors
- Preview mode: renderer uses demo data instead of API calls
- Production mode: renderer fetches from API

### UNDEFINED-005: License Verification Protocol
**References:** §38 (lines 1158-1188)
**Detail:** §38 says "periodic verification" but doesn't define the protocol.
**Proposed Resolution:**
- Client site calls `https://platform.example.com/api/v1/verify` every 24h
- Payload: `{ license_key, site_id, domain, platform_version }`
- Response: `{ valid: bool, expires_at: string, features: string[] }`
- Cache result for 24h; fail open (site stays up) if platform unreachable

### UNDEFINED-006: Ad Server Integration
**References:** §36 (lines 1112-1133)
**Detail:** §36 describes ad positions but doesn't define how ads are served.
**Proposed Resolution:**
- MVP: static HTML ads (Google AdSense snippets)
- Phase 2: integrate with ad server (Revive Adserver)
- Ad configuration stored in `platform.lock` features

### UNDEFINED-007: Search Ranking Algorithm
**References:** §37 (lines 1136-1156)
**Detail:** §37 says "MySQL/MariaDB search" but doesn't define ranking.
**Proposed Resolution:**
- Use MySQL full-text search with `MATCH ... AGAINST`
- Ranking: title match > excerpt match > body match
- No relevance tuning in MVP

### UNDEFINED-008: Editorial Workflow States
**References:** §7 (lines 267-272)
**Detail:** §7 lists post statuses (draft, scheduled, published, archived) but doesn't define transitions or permissions.
**Proposed Resolution:**
- State machine: `draft → scheduled → published → archived`
- Transitions: `draft → publish`, `draft → schedule`, `scheduled → publish`, `published → archive`
- Permissions: editor can draft/publish, admin can archive

### UNDEFINED-009: Media Storage Strategy
**References:** §8 (lines 300-340)
**Detail:** §8 describes media requirements but doesn't define storage backend.
**Proposed Resolution:**
- MVP: local filesystem (`storage/app/public/media`)
- Phase 2: S3-compatible storage (MinIO for self-hosted)
- Use Laravel's filesystem abstraction

### UNDEFINED-010: Menu System Data Structure
**References:** §6 (line 228)
**Detail:** §6 lists "Menus" as an entity but never defines the structure.
**Proposed Resolution:**
```json
{
  "id": 1,
  "name": "Main Navigation",
  "items": [
    { "label": "Home", "url": "/", "children": [] },
    { "label": "Sports", "url": "/category/sports", "children": [] }
  ]
}
```

### UNDEFINED-011: SEO Structured Data Format
**References:** §34 (lines 1065-1086)
**Detail:** §34 lists structured data types but doesn't define the JSON-LD format.
**Proposed Resolution:**
- Article: `https://schema.org/Article`
- BreadcrumbList: `https://schema.org/BreadcrumbList`
- Use `spatie/schema-org` Laravel package

### UNDEFINED-012: Newsletter System
**References:** §9 (line 364)
**Detail:** §9 lists a "Newsletter" component but doesn't define the backend.
**Proposed Resolution:**
- Store subscribers in `newsletter_subscribers` table
- Integrate with Mailchimp/SES for sending
- Unsubscribe link in every email

### UNDEFINED-013: Social Sharing Implementation
**References:** §34 (line 1075)
**Detail:** §34 mentions "Twitter/social cards" but doesn't define sharing buttons.
**Proposed Resolution:**
- Use `react-share` component
- Open Graph meta tags in `<head>`
- Twitter Card meta tags

### UNDEFINED-014: Pagination Strategy
**References:** §9 (line 368)
**Detail:** §9 lists a "Pagination" component but doesn't define the strategy.
**Proposed Resolution:**
- MVP: offset pagination (`?page=2`)
- Phase 2: cursor pagination for infinite scroll
- API returns `{ data: [], meta: { current_page, last_page } }`

---

## 5. Incomplete Specs (INCOMPLETE)

### INCOMPLETE-001: User Roles & Permissions Matrix
**References:** §5 (lines 184-187), §45 (lines 1349-1352)
**Detail:** §5 lists "Roles" and "Permissions" but never defines the matrix.
**Proposed Resolution:** Define in `docs/backend/entities.md`:

| Permission | Admin | Editor | Author | Viewer |
|-----------|-------|--------|--------|--------|
| posts.create | ✓ | ✓ | ✓ | ✗ |
| posts.edit.own | ✓ | ✓ | ✓ | ✗ |
| posts.edit.any | ✓ | ✓ | ✗ | ✗ |
| posts.publish | ✓ | ✓ | ✗ | ✗ |
| posts.delete | ✓ | ✗ | ✗ | ✗ |
| categories.manage | ✓ | ✗ | ✗ | ✗ |
| media.upload | ✓ | ✓ | ✓ | ✗ |
| media.delete.any | ✓ | ✗ | ✗ | ✗ |
| settings.manage | ✓ | ✗ | ✗ | ✗ |

### INCOMPLETE-002: API Endpoint Specification
**References:** §5 (lines 182-206)
**Detail:** §5 lists Laravel responsibilities but no API endpoints.
**Proposed Resolution:** Define in `docs/backend/api.md`:
- `GET /api/v1/posts` — list posts (public)
- `GET /api/v1/posts/{slug}` — single post (public)
- `GET /api/v1/categories` — list categories (public)
- `GET /api/v1/search` — search (public)
- `POST /api/v1/admin/posts` — create post (auth)
- `PUT /api/v1/admin/posts/{id}` — update post (auth)
- `DELETE /api/v1/admin/posts/{id}` — delete post (auth)
- etc.

### INCOMPLETE-003: Component Props Contract
**References:** §10 (lines 371-396)
**Detail:** §10 lists component files but doesn't define the props interface.
**Proposed Resolution:** Define in `docs/frontend/component-system.md`:
```typescript
interface NewsGridProps {
  title: string;
  category?: string;
  columns: 2 | 3 | 4;
  limit: number;
  items: NewsItem[];
}
```

### INCOMPLETE-004: Theme Configuration Schema
**References:** §15 (lines 509-534)
**Detail:** §15 describes theme contents but no configuration schema.
**Proposed Resolution:** Define in `docs/frontend/theme-system.md`:
```json
{
  "colors": { "primary": "#C00000", "secondary": "#222" },
  "typography": { "heading": "Inter", "body": "Source Sans" },
  "layout": { "container": "1200px", "sidebar": "right" }
}
```

### INCOMPLETE-005: Deployment Package Structure
**References:** §27 (lines 888-912)
**Detail:** §27 lists generated project files but no directory structure.
**Proposed Resolution:** Define in `docs/deployment/shared-hosting.md`:
```
client-site/
├── app/
│   ├── Http/Controllers/    # Public controllers
│   ├── Models/              # Eloquent models
│   └── ...
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   │   ├── platform/        # Platform-controlled
│   │   └── client/          # Client-controlled
│   └── seeders/
├── public/
│   ├── index.php
│   ├── assets/              # Compiled React assets
│   └── platform-assets/     # Platform-managed assets
├── resources/
│   ├── js/                  # React runtime
│   ├── views/               # Blade templates
│   └── css/
├── routes/
├── storage/
├── composer.json
├── package.json
├── platform.lock
└── .env.example
```

### INCOMPLETE-006: Health Check Specification
**References:** §31 (line 1010), §45 (line 1360)
**Detail:** §31 mentions "health checks" but doesn't define them.
**Proposed Resolution:** Define in `docs/deployment/update-system.md`:
- `GET /api/health` → `{ status: "ok", db: true, storage: true }`
- Run after every update
- If health check fails → automatic rollback

### INCOMPLETE-007: Rollback Procedure
**References:** §31 (lines 1016-1019)
**Detail:** §31 mentions "Rollback" but doesn't define the procedure.
**Proposed Resolution:** Define in `docs/deployment/update-system.md`:
1. Restore database from backup
2. Restore `platform-assets/` from backup
3. Restore `platform.lock` from backup
4. Run health check
5. If health check fails → alert admin, keep rollback state

---

## 6. Recommended Phase Reordering

### Current Order (§48)
1. Core CMS
2. React Public Website ← **builds on non-existent component system**
3. Component System
4. Theme System
5. Visual Builder
6. Generator
7. Deployment
8. Update System
9. Licensing
10. Rust CLI

### Recommended Order
1. **Core CMS** (unchanged)
2. **Component System + Renderer + Modern Theme** (was 3+4)
3. **Public Website** using component system (was 2)
4. **Visual Builder** (was 5)
5. **Generator** (was 6)
6. **Deployment — Tier 1 / No SSH** (was 7)
7. **Update System** (was 8) — *post-MVP*
8. **Licensing** (was 9) — *post-MVP*
9. **Rust CLI** (was 10) — *post-MVP*
10. **VPS/Cloud + Enterprise Tiers** *(new — GAP-010, was implicit in §41 modes table)* — *post-MVP*

**MVP boundary:** End of Phase 6 (Deployment — Tier 1). Phases 7–10 are post-MVP.
**Tier sequencing (ADR-0012):** the capability layer lands in Phase 1 so Phase 10 ships tiers without touching application logic.

---

## 7. Proposed Documentation Structure

```
docs/
├── README.md                          # Index / navigation
├── gap-analysis.md                    # This file
├── architecture/
│   ├── overview.md                    # High-level architecture
│   ├── rendering-model.md             # GAP-001 resolution
│   ├── data-flow.md                   # Content/presentation separation
│   └── monorepo.md                    # Repo structure
├── backend/
│   ├── overview.md                    # Laravel responsibilities
│   ├── entities.md                    # Core entities + news domain
│   ├── media-system.md                # Media handling
│   ├── api.md                         # API endpoints
│   └── migrations.md                  # Migration strategy
├── frontend/
│   ├── overview.md                    # React stack
│   ├── component-system.md            # Components, registry, schema
│   ├── theme-system.md                # Themes, tokens, overrides
│   └── builder.md                     # Visual builder
├── generator/
│   ├── overview.md                    # What the generator does
│   ├── process.md                     # Generation pipeline
│   └── platform-lock.md              # Lock file format
├── deployment/
│   ├── overview.md                    # 3-tier deployment model
│   ├── shared-hosting.md              # Tier 1 — no-SSH constraints + web flows
│   ├── vps-cloud.md                   # Tier 2 — SSH, CLI, Docker (optional)
│   ├── enterprise.md                  # Tier 3 — K8s, Helm, multi-region
│   └── update-system.md               # Tier-branched update/rollback process
├── licensing/
│   └── overview.md                    # License model
├── client-customization/
│   └── overview.md                    # 4-level customization model
├── development/
│   ├── phases.md                      # Development phases (reordered)
│   ├── mvp.md                         # MVP scope
│   ├── testing.md                     # Testing strategy
│   └── golden-rules.md                # Engineering principles
└── decisions/
    ├── README.md                      # ADR index
    ├── ADR-0001-rendering-model.md     # GAP-001
    ├── ADR-0002-media-transcoding.md   # GAP-003
    ├── ADR-0003-asset-distribution.md  # GAP-004
    ├── ADR-0004-override-resolution.md # GAP-005
    └── ADR-0005-comments-scope.md      # GAP-008
```

---

## 8. Immediate Next Steps

1. **Review this gap analysis** — confirm severity ratings and proposed resolutions
2. **Write ADRs** for all 🔴 CRITICAL items (GAP-001 through GAP-010) — ✅ *12/12 written and accepted (2026-09-30)*
3. **Resolve open decisions** ✅ *(decided 2026-09-30):*
   - Multi-language → **foundation-only**: locale-ready schema + translation layers, English ships; Bengali/RTL post-MVP (ADR-0009)
   - Comments → **out of MVP**, Phase 7+ feature (ADR-0005)
   - Rendering model → **hybrid** Blade + React islands + build-time SSG (ADR-0001)
   - Tier sequencing → **Tier 1 first**, capability layer built in Phase 1 (ADR-0012)
4. **Restructure docs** — split `plan.txt` into the proposed structure ✅ (done — 30 docs under `docs/`)
5. **Begin Phase 1** — Core CMS (ADRs approved ✅ 2026-09-30)

---

*End of gap analysis.*
