# Update System

**Source:** §31-32, §44 (plan.txt)
**Gap:** GAP-004

---

## Overview

The update system is the core value proposition of the platform. It allows a platform change to propagate to many client websites without destroying client content, configuration, custom CSS, or theme overrides.

---

## Update Modes by Tier

The update *semantics* (backup → compat check → apply → migrate → health → lock) are identical across tiers. Only the **trigger and execution mechanism** differ, branching on `platform.lock → deployment_type`.

| Aspect | Tier 1 — Shared (no SSH) | Tier 2 — VPS/Cloud | Tier 3 — Enterprise |
|--------|--------------------------|--------------------|----------------------|
| Trigger | Web admin panel (one-click) | CLI `php artisan platform:update` / SSH / CI | GitOps tag bump / `helm upgrade` |
| Auth | Session + license token | SSH key / platform agent token | Registry pull + Git perms |
| File swap | Web endpoint extracts ZIP over HTTP | CLI extracts / `rsync` from CDN | New container image (immutable) |
| `vendor/` update | Prebuilt in ZIP (no Composer on server) | Composer in ZIP or on server | Rebuilt into image |
| Migrations | **Web migration runner** (`POST /admin/migrations/run`) | `php artisan migrate --force` | pre-upgrade K8s Job (helm hook) |
| Asset update | Download bundle from CDN → atomic swap | Same, or rebuild on server | Baked into image |
| Cache/optimize | Pre-cached at generate; web panel re-caches | `optimize` CLI | Baked into image |
| Worker restart | N/A (no long-running workers) | `supervisorctl restart` | Rolling pod restart |
| Rollback | Web panel → restore backup | `platform:rollback` CLI | `helm rollback` / image tag revert |
| Max downtime | Seconds–minutes (single node) | Seconds (rsync + reload) | Zero (rolling / canary) |

---

## Shared Update Flow

```
Client Update:
    Backup
        ↓
    Check compatibility (platform.lock vs. target)
        ↓
    Download update package / pull image
        ↓
    Verify checksum / signature
        ↓
    Update platform-controlled files
        ↓
    Run migrations            ←─ tier-specific runner (below)
        ↓
    Update React assets       ←─ CDN bundle (T1/T2) or image (T3)
        ↓
    Run health checks
        ↓
    Success → Update platform.lock
        ↓
    Failure → Rollback
```

### Migration runner per tier

| Tier | Runner |
|------|--------|
| 1 | `POST /admin/migrations/run` — web endpoint invokes migration logic **without** requiring artisan/SSH; guarded by license token + admin auth + one-time lock; returns JSON progress; falls back to deferred batch runner for long migrations |
| 2 | `php artisan migrate --force` (invoked by CLI or platform remote-agent) |
| 3 | K8s Job (`job-migration.yaml`, `pre-upgrade` hook) with DB advisory lock |

### File swap per tier

| Tier | Mechanism |
|------|-----------|
| 1 | Extract new ZIP to `platform-update.tmp/` → verify → swap platform-controlled paths → keep client-controlled paths → atomic `platform.lock` write |
| 2 | Same as Tier 1, executed by CLI; or `composer install` + `npm run build` when toolchain present |
| 3 | No in-place swap — new immutable image is the update; old ReplicaSet retained for instant rollback |

---

## Update Components

### 1. Backup
Before every update:
1. Database backup
2. Storage/media backup
3. Configuration backup
4. Current version metadata

### 2. Compatibility Check
- Check platform version compatibility
- Check theme version compatibility
- Check component version compatibility
- Check migration compatibility

### 3. Download Update
- Download new asset bundle from platform CDN
- Verify checksum
- Extract to temporary directory

### 4. Update Packages
- Replace platform-controlled files
- Do NOT touch client-controlled files
- Run new migrations

### 5. Update React Assets
- **Problem:** No Node.js on shared hosting (Tier 1, §41)
- **Solution:** Platform hosts prebuilt asset bundles per version
- Tier 1/2: update system downloads prebuilt assets from CDN, no build step needed
- Tier 3: assets are baked into the container image; the image *is* the asset update

### 6. Health Check
```bash
GET /api/health
→ {"status":"ok","db":true,"storage":true}
```

### 7. Update Lock File
Update `platform.lock` with new versions.

---

## Platform-Controlled vs. Client-Controlled Files

| Platform-Controlled | Client-Controlled |
|---------------------|-------------------|
| Core Laravel code | Content (posts, categories, etc.) |
| Official themes | Configuration |
| Official components | Custom CSS |
| React runtime | Custom components |
| platform.lock | Theme overrides |
| | Client migrations |

**Rule:** Update must modify only platform-controlled files.

---

## Asset Distribution

### Problem
Component updates that change React source cannot be built on shared hosting (no Node.js).

### Solution
1. Platform hosts a **CDN or artifact repository** containing prebuilt asset bundles per (platform_version, component_version) pair
2. Generated sites ship with a `platform-assets/` directory containing compiled JS/CSS
3. Update process:
   - Download new asset bundle from platform CDN
   - Verify checksum
   - Atomic swap: move old assets to backup, extract new assets
   - Update `platform.lock`
   - Run health check
   - Rollback on failure

### Platform Build Pipeline
- CI builds all component combinations
- Publishes to CDN (Cloudflare R2 / S3)
- Cost: minimal (static file hosting)

---

## Rollback Procedure

The rollback path depends on tier:

| Tier | Rollback |
|------|----------|
| 1 | Web panel restores DB backup + `platform-assets/` + `platform.lock` from the pre-update snapshot |
| 2 | `php artisan platform:rollback` — restores same three artifacts, restarts workers, health check |
| 3 | `helm rollback` (previous ReplicaSet holds old assets) + DB PITR only if schema was changed |

Common steps (Tier 1/2):

1. Restore database from backup
2. Restore `platform-assets/` from backup
3. Restore `platform.lock` from backup
4. Run health check
5. If health check fails → alert admin, keep rollback state

---

## Update API

The update API is shared by all tiers. The response tells each tier *how* to apply the update.

### Check for Updates
```http
GET /api/v1/platform/updates?platform_version=3.2.1&deployment_type=shared
```

Response:
```json
{
  "available": true,
  "latest_version": "3.3.0",
  "changelog": "...",
  "compatible": true,
  "deployment_type": "shared",
  "apply_mode": "web_panel",
  "migration_strategy": "web_runner"
}
```

`apply_mode` per tier: `web_panel` (T1) | `cli` (T2) | `image_rollout` (T3)
`migration_strategy` per tier: `web_runner` | `artisan` | `k8s_job`

### Download Update
```http
POST /api/v1/platform/updates/download
Content-Type: application/json
Authorization: Bearer <license-token>

{
  "project_id": 1,
  "from_version": "3.2.1",
  "to_version": "3.3.0",
  "deployment_type": "shared"
}
```

Response (Tier 1/2 — file-based):
```json
{
  "download_url": "https://platform-cdn.example.com/updates/3.2.1-to-3.3.0-shared.zip",
  "checksum": "sha256:abc123...",
  "asset_bundle_url": "https://platform-cdn.example.com/assets/3.3.0.tar.gz",
  "expires_at": "2026-10-01T12:00:00Z"
}
```

Response (Tier 3 — image-based):
```json
{
  "image": "registry.example.com/newser-site",
  "tag": "3.3.0",
  "digest": "sha256:abc123...",
  "helm_chart_version": "3.3.0"
}
```

Tier-specific package suffixes are mandatory — a `shared` request must never receive a `vps` package (no `vendor/` guarantee differs, installer presence differs).

---

## Related Documents

- [Deployment Overview](./overview.md)
- [Shared Hosting — No SSH (Tier 1)](./shared-hosting.md)
- [VPS/Cloud (Tier 2)](./vps-cloud.md)
- [Enterprise (Tier 3)](./enterprise.md)
- [Platform Lock](../generator/platform-lock.md)
- [Gap Analysis](../gap-analysis.md) — GAP-004, GAP-009
