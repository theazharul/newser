# Development Phases

**Source:** §48 (plan.txt)
**Gap:** GAP-002 (reordered)

---

## Reordered Phases

The original plan (§48) builds the public website before the component system, which contradicts Golden Rule 19. The corrected order:

---

### Phase 1 — Core CMS

**Status:** ⏳ Not started

**Build:**
- Laravel
- Authentication
- Users, Roles, Permissions
- Posts, Categories, Tags, Authors
- Media
- Pages
- SEO
- Admin API
- **Capability layer** (`config/capabilities.php`) — derives `has_queue`, `has_redis`, `has_ssh_tooling`, `has_web_migrations`, `has_workers`, ... from `deployment_type` + env (ADR-0012)

**Exit criteria:**
- Can create/edit/publish posts via API
- Can upload and manage media
- Can manage categories and tags
- Roles and permissions enforced
- Capability layer exists and is consumed by queue/cache/media paths — **no `deployment_type === 'shared'` string checks anywhere** (GAP-010 sequencing rule: built first so Tier 2/3 later require zero app-logic changes)

---

### Phase 2 — Component System + Renderer + Modern Theme

**Status:** ⏳ Not started

**Build:**
- Component registry
- Component schema
- Renderer
- Data providers
- Versioning
- Initial components: Hero, BreakingNews, NewsCard, NewsGrid, NewsList, LatestNews, VideoGrid, Advertisement
- Modern theme

**Exit criteria:**
- Components render in a preview
- Component schema drives settings UI
- Data providers fetch from API
- Modern theme applied
- Component versioning works

---

### Phase 3 — Public Website

**Status:** ⏳ Not started

**Build:**
- React runtime
- Homepage
- Article page
- Category page
- Search
- Author page
- Navigation, Header, Footer
- Responsive layout

**Exit criteria:**
- All pages render with real content
- SEO meta tags present
- Structured data valid
- Responsive on mobile/tablet/desktop
- Fast TTFB (< 200ms)

---

### Phase 4 — Visual Builder

**Status:** ⏳ Not started

**Build:**
- Project creation
- Theme selector
- Component selector
- Drag/drop
- Component configuration
- Theme configuration
- Homepage sections
- Live preview
- Demo content

**Exit criteria:**
- Can create a project
- Can select Modern theme
- Can add/remove/reorder components
- Can configure components
- Can change colors
- Live preview works
- Demo content displays

---

### Phase 5 — Generator

**Status:** ⏳ Not started

**Build:**
- Project validation (incl. `deployment_type` + tier-feasibility checks)
- Laravel generation
- React runtime generation
- Package installation
- Theme installation
- Component installation
- Configuration generation
- platform.lock (writes `deployment_type`)
- Production asset build
- **Tier 1 packaging:** ZIP with `vendor/` + prebuilt assets + pre-cached config + web installer (no `node_modules`)
- ZIP generation

**Exit criteria:**
- Can generate a complete client site
- Generated site deploys to shared hosting **with zero shell commands**
- Generated site runs independently
- platform.lock created (includes `deployment_type`)

---

### Phase 6 — Deployment (Tier 1 — Shared Hosting / No SSH)

**Status:** ⏳ Not started

**Build:**
- Deployment package (Tier 1 artifact)
- **Web installer** (WordPress-style wizard: requirements → DB → site → `.env` + `APP_KEY` + migrations → lock)
- Environment configuration (generated in-browser)
- Database setup (via web installer)
- Storage setup (permission verification + File Manager guidance)
- **Web migration runner** (`POST /admin/migrations/run` — admin auth + CSRF + one-time lock)
- **HTTP cron endpoint** (`GET /api/cron/run?key=...`) + no-cron fallback
- Health check

**Exit criteria:**
- Can deploy to shared hosting **without SSH** (upload → open installer → live)
- Web migrations run artisan-free
- HTTP cron fires scheduled tasks
- Health check passes
- Site is live
- Tier 1 CI fixture + real-host smoke test green (see [Testing](./testing.md))

---

### Phase 7 — Update System (Post-MVP)

**Status:** ⏳ Not started

**Build:**
- Platform versions
- Theme versions
- Component versions
- Compatibility checking
- Migration system
- Backup
- Update process
- Health check
- Rollback
- Lock file updates

---

### Phase 8 — Licensing (Post-MVP)

**Status:** ⏳ Not started

**Build:**
- Client management
- Site management
- Domain management
- License generation
- Feature flags
- Periodic verification

---

### Phase 9 — Rust CLI (Post-MVP)

**Status:** ⏳ Not started

**Build:**
- news-cli
- Commands: news new, news build, news update, news deploy, news backup, news rollback, news doctor, news upgrade

**Note:** Rust is optional. Do NOT introduce Rust into the core system until there is a concrete reason.

---

### Phase 10 — VPS/Cloud + Enterprise Tiers (Post-MVP)

**Status:** ⏳ Not started
**Refs:** GAP-010, ADR-0012, [VPS/Cloud](../deployment/vps-cloud.md), [Enterprise](../deployment/enterprise.md)

**Sequencing rule:** Phases 1–6 build the capability layer and Tier 1 only. Tier 2/3 arrive here as **packaging + ops work — zero changes to application logic** (that was the point of building capabilities first).

**Build:**
- Tier 2 packaging: `deploy/provision.sh`, `deploy/update.sh`, optional `docker/` compose
- Supervisor + Horizon worker profiles, Redis queue/cache enablement (capability flip: `has_queue=true`)
- CLI update path (`platform:update`, `platform:rollback`)
- Tier 3 packaging: multi-stage Dockerfile, Helm chart (Deployment, Ingress, migration Job, CronJob, HPA, PDB)
- Image build/push pipeline + GitOps rollout
- Tier 2/3 update API responses (CLI metadata / image + chart versions)
- Observability: probes, structured logs, metrics endpoints

**Exit criteria:**
- Same codebase ships all three tiers (capability tests parameterized over tier sets)
- Tier 2 site deploys via CLI and runs queued workers
- Tier 3 site deploys via Helm with zero-downtime rolling update + pre-upgrade migration Job
- No `deployment_type` string branches added to app code (capabilities only)

---

## MVP Boundary

**MVP ends at Phase 6 (Deployment — Tier 1).** Phases 7–10 are post-MVP.

**Tier sequencing (ADR-0012):** MVP ships Tier 1 (`shared`). Tiers 2/3 (Phases 10+) must not require rework of Phases 1–6 — if they do, the capability layer was bypassed and that is a regression, not a scope change.

---

## Related Documents

- [MVP Scope](./mvp.md)
- [Testing Strategy (tier matrix)](./testing.md)
- [Golden Rules](./golden-rules.md)
- [Gap Analysis](../gap-analysis.md) — GAP-002, GAP-010
- [Architecture Overview](../architecture/overview.md)
- [Deployment Overview (3 tiers)](../deployment/overview.md)
- [Decisions — ADR-0012](../decisions/README.md)
