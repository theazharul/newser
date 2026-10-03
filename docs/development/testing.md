# Testing Strategy

**Source:** §46 (plan.txt)

---

## Testing Pyramid

```
        /\
       /  \     E2E (Playwright)
      /----\
     /      \   Feature (Pest)
    /--------\
   /          \  Unit (Pest / Vitest)
  /------------\
```

---

## Deployment Tier Matrix (GAP-010 / ADR-0012)

Three deployment tiers mean **three environment contracts** to test against. Code branches on capabilities (`has_queue`, `has_ssh_tooling`, ...), so tests must assert behavior per capability set — not per tier string.

| Test concern | Tier 1 `shared` | Tier 2 `vps` | Tier 3 `enterprise` |
|---|---|---|---|
| Capability derivation | `capabilities.php` returns no-queue/no-ssh set | full set | full set + orchestrator flags |
| Migrations | web runner endpoint (auth + CSRF + lock) | `artisan migrate` path | Job-wrapper path (unit-level) |
| Updates | web panel flow, file swap, rollback | CLI flow | image-swap flow (unit-level) |
| Cron | HTTP cron endpoint + no-cron fallback | scheduler command | N/A in tests |
| Queue/media | **sync path** (no workers) | queued path + `queue:work --one` | queued path |
| Cache | file cache | Redis | Redis |
| Packaging | ZIP contains `vendor/`, installer, prebuilt assets; **no** `node_modules` | ZIP contains deploy scripts | Docker/Helm metadata |

### Tier 1 constrained fixture (CI job)

The Tier 1 contract cannot be assumed on a normal dev machine — CI runs a dedicated fixture:

```yaml
  tier1-fixture:
    runs-on: ubuntu-latest
    env:                    # simulate shared hosting
      CACHE_STORE: file
      SESSION_DRIVER: file
      QUEUE_CONNECTION: sync
      DEPLOYMENT_TYPE: shared
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2' }
      - run: composer install --no-dev --optimize-autoloader
      - name: Assert no shell-only paths are required
        run: php artisan test --filter=NoSsh
      - name: Web migration runner + installer tests
        run: ./vendor/bin/pest --testsuite=tier1
```

**Rules for Tier 1 tests:**
- No `artisan` invocations in the tested code path (the web runner must work artisan-free)
- No Redis, no workers, file cache only
- Long-running operations must chunk under `max_execution_time`

### Real shared-host smoke test (per release)

A fixture cannot prove a real host behaves the same (open_basedir, `disable_functions`, mod_rewrite quirks). **Every release candidate** runs a manual/scripted smoke test against a real shared-hosting account:

1. Upload ZIP via File Manager → run web installer
2. Create post, upload media, change theme color
3. Run web migration endpoint with a new platform migration
4. One-click update → verify content/overrides intact
5. Rollback → verify restoration

**Record result:** pass/fail + host + PHP version in the release notes.

### Capability-layer tests

- Unit: `capabilities.php` derivation per `deployment_type` value
- Feature: same feature test parameterized over capability sets (e.g. "media upload" runs sync-when-no-queue, queued-when-queue)
- **Forbidden:** tests that assert on `deployment_type === 'shared'` — always assert on capabilities

---

## Backend Unit Tests

**Framework:** Pest PHP

**Test:**
- Post logic
- Permissions
- Component configuration
- Theme configuration
- Version compatibility
- Licensing
- Generator

**Location:** `tests/unit/`

---

## Backend Feature Tests

**Framework:** Pest PHP

**Test:**
- Login
- Post creation
- Publishing
- Category management
- Media
- Homepage
- Preview
- Generation
- Deployment
- Update
- Rollback

**Location:** `tests/feature/`

---

## Frontend Unit Tests

**Framework:** Vitest

**Test:**
- Components
- Configuration schemas
- Theme rendering
- Builder state
- Renderer

**Location:** `packages/*/tests/`

---

## E2E Tests

**Framework:** Playwright

**Test:**
- Create project
- Select theme
- Add component
- Configure component
- Preview
- Generate
- Deploy
- Update

**Location:** `tests/acceptance/`

---

## Test Naming Convention

```
tests/
├── unit/
│   ├── PostTest.php
│   ├── CategoryTest.php
│   └── ...
├── feature/
│   ├── AuthTest.php
│   ├── PostManagementTest.php
│   └── ...
└── acceptance/
    ├── ProjectCreationTest.php
    ├── ThemeSelectionTest.php
    └── ...
```

---

## Acceptance Tests (Per Global Workflow)

One test per acceptance criterion (behavioral, given/when/then, hitting real entry points):

```php
// tests/acceptance/ProjectCreationTest.php
test('user can create a project', function () {
    // Given: authenticated user
    $user = User::factory()->create();
    
    // When: user creates a project
    $response = $this->actingAs($user)
        ->post('/api/v1/admin/projects', [
            'name' => 'Test News',
            'domain' => 'testnews.com',
        ]);
    
    // Then: project is created
    $response->assertStatus(201);
    $this->assertDatabaseHas('projects', [
        'name' => 'Test News',
        'domain' => 'testnews.com',
    ]);
});
```

---

## CI Pipeline

```yaml
# .github/workflows/test.yml
name: Test

on: [push, pull_request]

jobs:
  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install
      - name: Run unit tests
        run: ./vendor/bin/pest --testsuite=unit
      - name: Run feature tests
        run: ./vendor/bin/pest --testsuite=feature

  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - name: Install dependencies
        run: npm install
      - name: Run unit tests
        run: npm run test
      - name: Run E2E tests
        run: npm run test:e2e

  # Tier 1 contract: shared hosting has NO ssh/node/redis/workers.
  # See "Deployment Tier Matrix" above.
  tier1-fixture:
    runs-on: ubuntu-latest
    env:
      DEPLOYMENT_TYPE: shared
      CACHE_STORE: file
      SESSION_DRIVER: file
      QUEUE_CONNECTION: sync
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install (production deps only, like a shipped package)
        run: composer install --no-dev --optimize-autoloader
      - name: Capability derivation tests
        run: ./vendor/bin/pest --filter=Capabilit
      - name: No-SSH paths (web installer, web migrations, HTTP cron)
        run: ./vendor/bin/pest --testsuite=tier1
```

**Release gate:** Tier 1 fixture job must be green, plus the real shared-host smoke test (below) recorded, before any package ships.

---

## Test Coverage Targets

| Layer | Target |
|-------|--------|
| Backend unit | 80% |
| Backend feature | 70% |
| Frontend unit | 70% |
| E2E | Critical paths only |

---

## Related Documents

- [Development Phases](./phases.md)
- [MVP Scope](./mvp.md)
- [Golden Rules](./golden-rules.md)
- [Deployment Overview (3 tiers)](../deployment/overview.md)
- [Shared Hosting — No SSH (Tier 1)](../deployment/shared-hosting.md)
- [Gap Analysis](../gap-analysis.md) — GAP-010
- [Decisions — ADR-0012](../decisions/README.md)
