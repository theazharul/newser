# Generation Process

**Source:** §40 (plan.txt)

---

## Pipeline

```
Project Configuration
        ↓
    Validate  (incl. deployment_type)
        ↓
Create Laravel application
        ↓
Install backend packages
        ↓
Install React runtime
        ↓
Install selected theme
        ↓
Install selected components
        ↓
Apply configuration
        ↓
Create migrations
        ↓
Create platform.lock  (writes deployment_type)
        ↓
Build React assets
        ↓
Optimize Laravel
        ↓
Run tests
        ↓
┌──── Package by deployment_type ────┐
│ shared     → ZIP (+vendor/ +installer +pre-cached config)
│ vps        → ZIP + deploy/ scripts (+vendor/, no installer)
│ enterprise → Docker image + Helm chart
└─────────────────────────────────────┘
```

---

## Step-by-Step

### 1. Validate Configuration
- Check theme exists and is compatible
- Check components exist and are compatible
- Check feature flags are valid
- Check domain is valid
- Check `deployment_type` is one of `shared` | `vps` | `enterprise`
- Check tier feasibility: e.g. `shared` rejects `queue=redis`, `enterprise` requires `docker=true`

### 2. Create Laravel Application
```bash
composer create-project laravel/laravel client-site
```

### 3. Install Backend Packages
```bash
composer require laravel/sanctum
composer require spatie/laravel-medialibrary
composer require spatie/laravel-permission
# ... other platform packages
```

### 4. Install React Runtime
```bash
cd client-site
npm install
npm install @news/runtime @news/theme-engine @news/components
```

### 5. Install Selected Theme
```bash
npm install @news/theme-modern
```

### 6. Install Selected Components
```bash
npm install @news/component-hero @news/component-news-grid # ...
```

### 7. Apply Configuration
- Copy theme files to `resources/`
- Copy component files to `resources/js/components/`
- Generate `config/platform.php`
- Generate `.env` from `.env.example`

### 8. Create Migrations
- Copy platform migrations to `database/migrations/platform/`
- Run `php artisan migrate`

### 9. Create platform.lock
```json
{
  "platform": "3.2.1",
  "generator": "1.4.0",
  "core": "3.2.1",
  "deployment_type": "shared",
  "theme": { "name": "modern", "version": "2.4.0" },
  "components": {
    "hero": "2.0.0",
    "news-grid": "3.1.0"
  },
  "migration_version": "2026_09_01_000000",
  "asset_bundle": "sha256:abc123...",
  "features": { "comments": false, "video": true }
}
```

### 10. Build React Assets
```bash
npm run build
```

### 11. Optimize Laravel
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 12. Run Tests
```bash
php artisan test
npm run test
```

### 13. Package by Deployment Tier

The packaging step branches on `deployment_type` written to `platform.lock`:

**Tier 1 — `shared` (no SSH):**
```bash
composer install --no-dev --optimize-autoloader     # vendor/ goes INTO the ZIP
npm run build                                        # already done in step 10
php artisan config:cache route:cache view:cache      # caches baked in
zip -r client-site-shared.zip client-site/ \
  -x "*/node_modules/*" -x "*/.git/*" -x "*/tests/*"
```
Package contains: `vendor/`, prebuilt assets, pre-cached config, web `installer/`, `.htaccess`, `platform.lock`.
Package omits: `node_modules/`, dev deps, source maps.

**Tier 2 — `vps`:**
```bash
composer install --no-dev --optimize-autoloader
zip -r client-site-vps.zip client-site/ \
  -x "*/node_modules/*" -x "*/.git/*"
```
Package contains: `vendor/`, `deploy/provision.sh`, `deploy/update.sh`, optional `docker/`.
Omits: web installer (CLI is available).

**Tier 3 — `enterprise`:**
```bash
docker build -t registry.example.com/newser-site:${GIT_SHA} .
docker push registry.example.com/newser-site:${GIT_SHA}
helm package ./deploy/helm/newser-site --version ${VERSION}
```
Artifact: container image + Helm chart (no ZIP).

---

## Validation Rules

| Rule | Description |
|------|-------------|
| Theme exists | Theme must be registered in theme registry |
| Components exist | All components must be in component registry |
| Compatibility | Theme + components must be compatible |
| Feature flags | Must be valid feature names |
| Domain | Must be valid domain format |
| Deployment type | Must be `shared`, `vps`, or `enterprise` |
| Tier feasibility | Config must not require capabilities absent from the chosen tier (e.g. Redis queue on `shared`) |

---

## Error Handling

| Error | Action |
|-------|--------|
| Validation failure | Return error, do not generate |
| Package installation failure | Retry once, then fail |
| Build failure | Return error with build log |
| Test failure | Return error with test output |

---

## Related Documents

- [Generator Overview](./overview.md)
- [Platform Lock](./platform-lock.md)
- [Deployment Overview](../deployment/overview.md)
- [Shared Hosting (Tier 1 packaging)](../deployment/shared-hosting.md)
- [VPS/Cloud (Tier 2 packaging)](../deployment/vps-cloud.md)
- [Enterprise (Tier 3 packaging)](../deployment/enterprise.md)
