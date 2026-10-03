# AGENTS.md — newser (News Site Generation Platform)

## What this project is

A platform that builds and generates news websites for clients. **Decision-complete spec lives in `docs/`** — that is the source of truth, not memory, not plan.txt prose.

- `docs/README.md` — index; read first
- `docs/plan.txt` — original 1902-line plan. **Read-only reference. Never edit.**
- `docs/gap-analysis.md` — 47 tracked gaps (all known, none open)
- `docs/decisions/README.md` — 12/12 ADRs **Accepted (2026-09-30)**. Do not re-litigate. Changes = new ADR, never edit an accepted one.
- Phase scope + exit criteria: `docs/development/phases.md`
- Testing contract: `docs/development/testing.md`

## Mandatory workflow (every feature, no exceptions)

1. Read the acceptance criteria in `docs/` for the phase/task.
2. Write acceptance tests first in **`tests/acceptance/`** (root) — one per criterion, behavioral, hitting real entry points (`/api/v1/...`).
3. Run → must FAIL (red). Passing-on-write means the test is wrong.
4. Write failing unit tests in **`tests/unit/`** (root).
5. Implement minimal code to pass (follow existing patterns; read sibling files).
6. Full suite green. **Done = green + no scope creep.** Self-report is not evidence.
7. Report: files changed, X acceptance / Y unit passing, red→green proof.

Hard rules: fix implementation, never the test; never modify acceptance tests written by others; no new dependencies beyond what `docs/` specifies; no `as any` / `@ts-ignore` (PHP: no error suppression); weak tests are worse than no tests.

## Repo layout (monorepo)

```
apps/platform/     Laravel backend (own composer.json — run artisan/composer/pest here)
tests/acceptance/  Pest acceptance tests (root — mandated location)
tests/unit/        Pest unit tests (root)
tests/feature/     Pest feature tests (root)
docs/              Spec (source of truth)
```

- Package manager for PHP: **Composer** (in `apps/platform/`). Frontend (Phase 3+): **pnpm** workspaces — see `docs/architecture/monorepo.md`.
- Test framework: **Pest** (`docs/development/testing.md`). CI runs suites as `--testsuite=unit|feature|acceptance|tier1`.

## Commands

```bash
# PHP env
cd apps/platform
composer install
php artisan --version
php artisan migrate            # dev DB: MySQL in Docker (see below)

# Tests (wired to root tests/ via phpunit.xml — suites: acceptance, unit, feature)
cd apps/platform
./vendor/bin/pest --testsuite=acceptance
./vendor/bin/pest --testsuite=unit
./vendor/bin/pest               # everything
```

## Environment (dev machine facts)

- PHP 8.4 + GD/WebP ✓, Composer 2.8, Node 22. **No native MySQL, no sudo.**
- MySQL runs in Docker: container `newser-mysql`, host port **3307**,
  db `newser` (dev) / `newser_test` (tests), user/pass `newser`/`newser`
  (root pass: `newserroot`). Start: `docker start newser-mysql`
- Pest note: Laravel 13 skeleton pins `phpunit ^12` — use `pestphp/pest:^5.0`-compatible resolution (`composer require -W`).

## Architecture rules that bite if ignored

- **Capability layer (ADR-0012):** app code must NEVER contain `deployment_type === 'shared'` (or any tier string comparison). Branch on capabilities from `config/capabilities.php` (`has_queue`, `has_redis`, `has_ssh_tooling`, `has_web_migrations`, `has_workers`, `has_node`, `has_web_installer`). There is a test that scans for this — it will fail the build.
- **Tiers:** `shared` (MVP, no SSH, no workers, sync + cron) → `vps` → `enterprise`. MVP ships Tier 1 only.
- **Migrations:** append-only, idempotent (`Schema::hasTable/hasColumn` guards), live in `database/migrations/platform/` (+ `client/` for client custom). Never modify a released migration.
- **Queue:** no workers on Tier 1. Media/variant work is synchronous behind a capability check (ADR-0010). DB queue driver, no Redis in MVP.
- **API:** Sanctum bearer auth, base path `/api/v1`, admin routes under `/api/v1/admin/*`, error shape `{"error":{code,message,details}}` — see `docs/backend/api.md`.
- **Roles:** admin / editor / author / viewer with permission names (`posts.create`, …) — see `docs/backend/entities.md`.
- **Golden rules:** `docs/development/golden-rules.md` (20 rules) — especially: never let clients edit platform core; content ≠ presentation; build React assets before shared-hosting deploys.

## Scope discipline

- Phase scope is whatever `docs/development/phases.md` says for that phase. Post-MVP = Phases 7–10. Do not build ahead.
- MVP boundary: ends at Phase 6 (Tier 1 deployment). Explicitly NOT in MVP: comments, i18n beyond locale-ready foundation, Redis, K8s, Elasticsearch, Rust.
