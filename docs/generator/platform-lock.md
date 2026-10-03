# Platform Lock File

**Source:** §29 (plan.txt)
**Gap:** GAP-006

---

## Purpose

The `platform.lock` file is the source of truth for "what is this client running." It enables safe updates by telling the platform exactly which versions the client is running.

---

## Lock File Format

```json
{
  "platform": "3.2.1",
  "generator": "1.4.0",
  "core": "3.2.1",
  "deployment_type": "shared",
  "theme": {
    "name": "modern",
    "version": "2.4.0"
  },
  "components": {
    "hero": "2.0.0",
    "news-grid": "3.1.0",
    "breaking-news": "1.4.0"
  },
  "client_overrides": {
    "header": {
      "base_version": "1.2.0",
      "override_id": "abc123"
    }
  },
  "migration_version": "2026_09_01_000000",
  "asset_bundle": "sha256:abc123...",
  "features": {
    "comments": false,
    "video": true,
    "newsletter": true
  }
}
```

---

## Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `platform` | string | Platform version (semver) |
| `generator` | string | Generator version used to create the site |
| `core` | string | Core engine version (may differ from platform) |
| `deployment_type` | string | Deployment tier: `shared` \| `vps` \| `enterprise` |
| `theme` | object | Theme name + version |
| `components` | object | Map of component name → version |
| `client_overrides` | object | Client-authored component overrides |
| `migration_version` | string | Last applied migration timestamp |
| `asset_bundle` | string | SHA-256 checksum of asset bundle |
| `features` | object | Enabled feature flags |

---

## Deployment Type

`deployment_type` is written once at generation time and **never changes** for the lifetime of the site (migrating tiers requires a re-provision, not an update).

```json
"deployment_type": "shared"   // "shared" | "vps" | "enterprise"
```

### Who reads it

| Consumer | Behavior |
|----------|----------|
| **Generator** | Selects packaging step (ZIP + `vendor/` + installer vs. deploy scripts vs. Docker/Helm) |
| **Update system** | Chooses apply mode: `web_panel` (T1) \| `cli` (T2) \| `image_rollout` (T3) |
| **Migration runner** | Chooses web runner (T1) \| artisan (T2) \| K8s Job (T3) |
| **Update API** | Server returns a tier-specific package — a `shared` site must never receive a `vps`/`enterprise` package |
| **Application runtime** | Consults derived **capabilities**, not the tier string directly |

### Capabilities over tier checks

Application code should never branch on `deployment_type === 'shared'`. Instead, `capabilities.php` derives a capability object at boot:

```php
// config/capabilities.php (derived from deployment_type + env probing)
[
    'has_ssh_tooling' => false,   // artisan/composer usable at runtime
    'has_queue'       => false,   // Redis queue available
    'has_redis'       => false,
    'has_node'        => false,
    'has_workers'     => false,   // long-running processes allowed
    'has_web_installer' => true,
    'has_web_migrations' => true,
]
```

Code asks **"can I do X?"** (`if ($caps['has_queue'])`) rather than **"which tier am I?"**. This keeps a single codebase across tiers and makes a new tier (e.g. `hybrid`) additive rather than invasive.

Default derivation:

| Capability | shared | vps | enterprise |
|------------|--------|-----|------------|
| `has_ssh_tooling` | ❌ | ✅ | ✅ (or GitOps) |
| `has_queue` | ❌ (sync + cron) | ✅ | ✅ |
| `has_redis` | ❌ | ✅ | ✅ |
| `has_node` | ❌ | ✅ | CI-only |
| `has_workers` | ❌ | ✅ | ✅ |
| `has_web_installer` | ✅ | ✅ (optional) | ❌ |
| `has_web_migrations` | ✅ | ✅ | ❌ (K8s Job) |

**See:** [ADR-0012 Deployment Tiers](../decisions/README.md), [ADR-0011 No-SSH Deployment](../decisions/README.md#adr-0011-no-ssh-deployment)

---

## Version Fields

### Platform vs. Core vs. Generator

| Field | What it versions | When it changes |
|-------|-----------------|-----------------|
| `platform` | The central platform (API, admin, builder) | Any platform change |
| `core` | The client site's core engine (Laravel + React runtime) | Core engine change |
| `generator` | The site generator | Generator change |

**Why separate?** The platform may update without requiring a client site update. The generator may update without changing the core engine.

---

## Client Overrides

Client overrides are tracked separately from platform components:

```json
{
  "client_overrides": {
    "header": {
      "base_version": "1.2.0",
      "override_id": "abc123"
    }
  }
}
```

- `base_version`: The platform component version the override was created against
- `override_id`: Unique identifier for the override

**Update behavior:**
- If `base_version` matches the new platform version → apply update cleanly
- If `base_version` differs → run 3-way merge
- Conflict → flag for manual resolution, keep client override

---

## Asset Bundle Checksum

The `asset_bundle` field contains a SHA-256 checksum of the compiled React assets. This ensures:
- Integrity: assets have not been tampered with
- Versioning: assets match the expected version
- Rollback: can verify backup assets

---

## Lock File Location

```
client-site/
├── platform.lock          # Lock file
├── public/
│   └── platform-assets/   # Platform-managed assets
└── ...
```

---

## Lock File Updates

The lock file is updated:
1. **On generation:** initial lock file created
2. **On update:** lock file updated with new versions
3. **On rollback:** lock file restored from backup

**Rule:** The lock file is platform-controlled. Clients never modify it directly.

---

## Related Documents

- [Generator Overview](./overview.md)
- [Generation Process](./process.md)
- [Update System](../deployment/update-system.md)
- [Deployment Overview](../deployment/overview.md)
- [Shared Hosting (Tier 1)](../deployment/shared-hosting.md)
- [Gap Analysis](../gap-analysis.md) — GAP-006, GAP-009
