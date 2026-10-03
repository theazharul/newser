# VPS/Cloud Deployment (Tier 2)

**Source:** §41-43 (plan.txt)
**Target:** Medium clients with SSH access

---

## When This Tier Applies

- Client has a VPS, cloud instance (DigitalOcean, Hetzner, AWS EC2, Linode), or managed VPS
- SSH access available to platform operator or client
- Full toolchain permitted: Composer, npm, Redis, Supervisor, systemd

`platform.lock` records `"deployment_type": "vps"`.

---

## Server Requirements

| Requirement | Minimum | Recommended |
|------------|---------|-------------|
| PHP | 8.2 (FPM) | 8.3 |
| Extensions | pdo_mysql, mbstring, openssl, zip, gd, intl, bcmath, opcache | + redis |
| MySQL/MariaDB | 8.0 / 10.6 | 8.0 |
| Redis | 6.0 | 7.0 |
| Node.js | 18 (optional, for on-server builds) | 20 |
| Composer | 2.x | 2.x |
| RAM | 1 GB | 2 GB |
| Disk | 10 GB SSD | 20 GB SSD |

---

## Provisioning

Automated via `deploy/provision.sh` (generated into every Tier 2 package):

```bash
#!/usr/bin/env bash
# deploy/provision.sh — idempotent server setup
set -euo pipefail

# 1. System packages
apt-get update
apt-get install -y nginx redis-server supervisor certbot python3-certbot-nginx \
    php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl \
    php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath php8.2-redis unzip git

# 2. PHP-FPM pool tuning
# 3. Nginx site + SSL (certbot)
# 4. Redis: bind 127.0.0.1, maxmemory policy allkeys-lru
# 5. Supervisor program: queue worker + Horizon
# 6. Cron: schedule:run every minute
```

---

## Deployment Steps

### 1. Package (CI or local)
```bash
# Tier 2 package includes vendor/ but no prebuilt requirement
composer install --no-dev --optimize-autoloader
npm run build                      # optional if shipping prebuilt
zip -r client-site-vps.zip . -x "node_modules/*" ".git/*"
```

### 2. Upload & Extract
```bash
scp client-site-vps.zip deploy@server:/opt/
ssh deploy@server 'cd /opt && unzip -q client-site-vps.zip -d /var/www/client-site'
```

### 3. Environment
```bash
cd /var/www/client-site
cp .env.example .env
php artisan key:generate
# Edit .env: DB_*, REDIS_*, APP_URL, QUEUE_CONNECTION=redis, CACHE_STORE=redis
php artisan config:cache route:cache view:cache
```

### 4. Migrations
```bash
php artisan migrate --force --path=database/migrations/platform
```

### 5. Permissions
```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 6. Queue Worker (Supervisor)
```ini
; /etc/supervisor/conf.d/newser-worker.conf
[program:newser-worker]
command=php /var/www/client-site/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/newser-worker.err.log
stdout_logfile=/var/log/newser-worker.out.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

### 7. Horizon
```bash
php artisan horizon:install
sudo supervisorctl start horizon
```

### 8. Cron
```cron
* * * * * www-data cd /var/www/client-site && php artisan schedule:run >> /dev/null 2>&1
```

### 9. Health Check
```bash
curl https://example.com/api/health
# {"status":"ok","db":true,"storage":true,"redis":true,"queue":"redis"}
```

---

## Docker Compose (Local Parity / Optional Server Runtime)

Tier 2 packages ship a `docker/` directory so developers and clients can run identical stacks locally:

```yaml
# docker/docker-compose.yml
services:
  app:
    build: { context: .., dockerfile: docker/Dockerfile }
    depends_on: [db, redis]
    environment:
      DB_HOST: db
      REDIS_HOST: redis
      QUEUE_CONNECTION: redis
      CACHE_STORE: redis
    ports: ["8080:80"]
  db:
    image: mysql:8.0
    environment: { MYSQL_DATABASE: newser, MYSQL_ROOT_PASSWORD: secret }
    volumes: [dbdata:/var/lib/mysql]
  redis:
    image: redis:7-alpine
  worker:
    build: { context: .., dockerfile: docker/Dockerfile }
    command: php artisan queue:work redis --sleep=3 --tries=3
    depends_on: [app, redis]
volumes:
  dbdata:
```

**Note:** Docker is *optional* on Tier 2 — it is a convenience for local dev and for clients who prefer it, not a requirement. Bare-metal + Supervisor remains the primary path.

---

## Updates (Tier 2)

Tier 2 uses CLI updates — no web installer required, though the web panel still works:

```bash
cd /var/www/client-site
php artisan platform:backup --type=full
php artisan platform:update            # checks platform.lock, applies ZIP/asset swap
php artisan migrate --force
php artisan config:cache route:cache view:cache
sudo supervisorctl restart newser-worker horizon
php artisan optimize
curl -fsS https://example.com/api/health
```

On failure → `php artisan platform:rollback`.

**Automation:** `deploy/update.sh` wraps the above; can be triggered by SSH, CI (Ansible/SSH action), or the platform's remote-agent pull model.

See [Update System](./update-system.md).

---

## Capability Matrix (Tier 2)

| Capability | Status |
|------------|--------|
| `has_ssh` | ✅ |
| `has_queue` | ✅ (Redis) |
| `has_redis` | ✅ |
| `has_node` | ✅ (optional) |
| `has_composer` | ✅ |
| `has_workers` | ✅ (Supervisor/Horizon) |
| `has_docker` | optional |
| `has_webscale_storage` | S3-compatible optional |

---

## Backup Strategy

| Type | Frequency | Method | Retention |
|------|-----------|--------|-----------|
| Database | Daily | `mysqldump` via cron → S3 | 30 days |
| Files/media | Daily incremental / weekly full | `restic` or `rsync` → S3 | 90 days |
| Full snapshot | Weekly | provider snapshot (do_snapshot etc.) | 4 weeks |

```cron
0 3 * * * www-data cd /var/www/client-site && php artisan platform:backup --type=db --target=s3
```

---

## Monitoring

- **Health:** `GET /api/health` polled by external checker (UptimeRobot / Healthchecks.io)
- **Logs:** `/var/log/nginx/`, Laravel `storage/logs/`, Supervisor logs — shipped via `vector` or `promtail` optional
- **Metrics:** optional node_exporter + Prometheus (client-managed)
- **Alerts:** email via health-check service for MVP

---

## Related Documents

- [Deployment Overview](./overview.md)
- [Shared Hosting (Tier 1)](./shared-hosting.md)
- [Enterprise (Tier 3)](./enterprise.md)
- [Update System](./update-system.md)
- [Generator Process](../generator/process.md)
