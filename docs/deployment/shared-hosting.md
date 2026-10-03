# Shared Hosting Deployment (Tier 1 — No SSH)

**Source:** §41 (plan.txt)
**Gap:** GAP-009
**Constraint:** Clients on shared hosting have **no SSH access**. Every operation — install, configure, migrate, update, rollback — must work from a browser.

---

## Constraints

| Constraint | Solution |
|-----------|----------|
| **No SSH / no terminal** | Web installer, web migration runner, web update panel |
| No Composer on server | Package ships with `vendor/` prebuilt |
| No Node.js on production | React assets built at generation time, shipped in package |
| No npm on production | No build step on server |
| No Docker | Traditional LAMP stack |
| No Redis | File-based caching |
| No long-running workers | Synchronous processing or HTTP-triggered cron |
| Cron available (cPanel GUI) | cPanel Cron Jobs UI or external cron service |

**Design rule:** if an operation requires a shell command, it must have a browser equivalent. See [ADR-0011 No-SSH Deployment](../decisions/README.md#adr-0011-no-ssh-deployment).

---

## Server Requirements

| Requirement | Minimum | Recommended |
|------------|---------|-------------|
| PHP | 8.1 | 8.2 |
| MySQL/MariaDB | 5.7 / 10.3 | 8.0 / 10.6 |
| Apache (mod_rewrite) | 2.4 | 2.4+ |
| Disk space | 1 GB | 5 GB |
| Bandwidth | 10 GB/mo | 50 GB/mo |
| cPanel or equivalent | optional | File Manager + Cron GUI |

**Out of scope for Tier 1:** Nginx-only, LiteSpeed-without-rewrite, any host without `mod_rewrite` → route to Tier 2.

---

## Deployment Steps (No SSH)

### 1. Build Package (Platform CI — not the client)
```bash
# Runs on the platform's build server, never on client hosting
composer install --no-dev --optimize-autoloader   # vendor/ goes INTO the ZIP
npm run build                                      # assets prebuilt
php artisan config:cache route:cache view:cache    # caches baked in
zip -r client-site-shared.zip client-site/ -x "*/node_modules/*" -x "*/.git/*"
```

### 2. Upload (client, via browser)
- **cPanel File Manager:** upload ZIP → Extract
- **or FTP/SFTP panel:** upload ZIP → extract via file manager
- **or** platform's browser uploader: `POST /admin/site/upload-package` (ZIP streamed server-side, no shell needed)

Document root must point at `public/` (cPanel → *Domains → document root*, or `.htaccess` handles it).

### 3. Open Installer (client, via browser)
Visit `https://example.com/install` — the web installer wizard runs entirely in PHP:

| Screen | What it does |
|--------|--------------|
| Requirements check | PHP version, extensions, `storage/` + `bootstrap/cache/` writable, MySQL reachable |
| Database | DB host/name/user/password → **tested live** before proceeding |
| Site | Site name, admin email, admin password |
| Generate | Writes `.env`, generates `APP_KEY`, runs migrations, seeds, writes `platform.lock` |
| Done | Locks installer (`install.lock`), redirects to `/admin` |

No `.env` editing, no `php artisan key:generate`, no shell — the installer does it all.

### 4. Permissions
The installer verifies writability and reports exact paths to fix in File Manager:

| Path | Permission |
|------|-----------|
| `storage/` and subdirs | 755 (files 644) |
| `bootstrap/cache/` | 755 |
| `.env` | 640 |

If a host allows `chmod` from PHP (`uopz`/`suexec` permitting), the installer attempts it automatically.

### 5. Migrations (web runner — no artisan)
```http
POST /admin/migrations/run
Authorization: Bearer <admin-session>
X-CSRF-Token: <token>

{ "path": "database/migrations/platform", "batch": 1 }
```

Response:
```json
{ "status": "completed", "applied": 42, "failed": 0, "duration_ms": 8123 }
```

- Guards: admin auth + CSRF + one-time lock (no concurrent runs)
- Long migrations stream progress via chunked response or poll `GET /admin/migrations/status`
- Client migrations run the same way from `/admin/database`

See [Backend Migrations](../backend/migrations.md).

### 6. Web Server Config (browser / no shell)
Apache ships `.htaccess` inside `public/` — no config edit required:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>
```

If the host needs `public/` as document root and cPanel offers no option, provide `public/.htaccess` rewrite from parent directory, or instruct via screenshot-based docs. Nginx-only hosts are **out of scope for Tier 1** (they are Tier 2).

### 7. Cron (no shell — three options)

| Option | How |
|--------|-----|
| **A. cPanel Cron GUI** | cPanel → Cron Jobs → command `php /home/USER/artisan schedule:run` (cPanel runs it server-side; client never touches a shell) |
| **B. External cron service** | cron-job.org / easyCron → `GET https://example.com/api/cron/run?key=<secret>` every 1–5 min |
| **C. In-app fallback** | Admin panel "Run scheduled tasks now" button + lazy trigger on page views when no cron configured |

Option B is the recommended default (works on every host, independent of panel).

The HTTP cron endpoint:
```http
GET /api/cron/run?key=<cron-secret>
→ {"status":"ok","tasks":["license:verify","backup:db","cleanup:temp"]}
```

### 8. Health Check
```bash
curl https://example.com/api/health
# {"status":"ok","db":true,"storage":true,"cron":"ok"}
```
Also surfaced in the admin dashboard (green/red badge) so the client sees it without curl.

### 9. Updates (web panel, one-click)
Admin → *Platform → Updates*:

1. Panel calls update API (checks `platform.lock`)
2. Downloads ZIP + asset bundle to `storage/updates/`
3. Verifies checksum
4. Creates backup (DB + assets + `platform.lock`)
5. Swaps platform-controlled files only
6. Runs web migrations
7. Swaps assets atomically
8. Health check → commit or rollback

Entirely browser-driven. See [Update System](./update-system.md).

---

## File Permissions

| Path | Permission | Owner |
|------|-----------|-------|
| `storage/` | 755 | account user |
| `bootstrap/cache/` | 755 | account user |
| `public/` | 755 | account user |
| `.env` | 640 | account user |

**No SSH note:** ownership cannot be changed via shell. On shared hosting the files are owned by the account user by default — the installer only verifies permissions and gives the client File Manager instructions when something is not writable. Never rely on `chown` in any Tier 1 flow.

---

## Backup Strategy

| Backup Type | Frequency | Storage | Retention |
|-----------|-----------|---------|-----------|
| Database | Daily | Platform cloud | 7 days |
| Files | Weekly | Platform cloud | 4 weeks |
| Full | Monthly | Platform cloud | 12 months |

**No-SSH backup triggers (all browser/HTTP based):**

1. **Web panel:** Admin → *System → Backups → Create backup* (synchronous, streamed to platform cloud)
2. **HTTP cron:** external cron service calls `GET /api/cron/run?key=<secret>` → scheduler runs `backup:db` / `backup:full` as jobs
3. **Pre-update automatic:** every one-click update snapshots DB + assets + `platform.lock` first

The `php artisan platform:backup --type=full` equivalent exists for Tier 2/3 only — Tier 1 must never require it.

---

## Related Documents

- [Deployment Overview](./overview.md)
- [VPS/Cloud (Tier 2)](./vps-cloud.md)
- [Enterprise (Tier 3)](./enterprise.md)
- [Update System](./update-system.md)
- [Backend Migrations (web runner)](../backend/migrations.md)
- [Generator Overview](../generator/overview.md)
- [Gap Analysis](../gap-analysis.md) — GAP-009
