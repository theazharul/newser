# Deployment Overview

**Source:** §41-43 (plan.txt)

---

## Deployment Tiers

The platform ships to three tiers. **The application architecture is identical across all tiers** — only the packaging, install, update, and operational tooling differ.

| Tier | Infrastructure | SSH | Use Case | Status |
|------|---------------|-----|----------|--------|
| **Tier 1 — Shared Hosting** | PHP + MySQL + Apache/Nginx + Cron | **No** | Small clients, MVP | MVP |
| **Tier 2 — VPS/Cloud** | Laravel + React + MySQL + Redis + Queue | Yes | Medium clients | Phase 2 |
| **Tier 3 — Enterprise** | Docker/K8s + DB + Redis + Queue + Storage + Monitoring | Yes | Large clients, SLAs | Phase 3 |

### Tier Selection

`platform.lock` records `deployment_type` (`shared` | `vps` | `enterprise`). The generator emits a different package per tier; the update system branches on this field.

| Capability | Shared (no SSH) | VPS/Cloud | Enterprise |
|------------|-----------------|-----------|------------|
| Install | Web installer | CLI + web installer | Helm chart / GitOps |
| `.env` + `APP_KEY` | Web installer generates | CLI / `.env` manually | Secrets manager |
| Migrations | Web endpoint (artisan-free) | `php artisan migrate` | Job/CronJob in cluster |
| Asset build | Prebuilt bundles from CDN | Prebuilt or build on server | CI builds image |
| Cron | cPanel cron or external cron service | system cron / systemd timer | K8s CronJob |
| Queue workers | None (sync + cron) | Supervisor + Redis | K8s Deployment + HPA |
| Updates | Web admin panel (one-click) | CLI `platform:update` | `git pull` / image rollout |
| Rollback | Web panel (backup restore) | CLI + snapshot | Image tag rollback |
| Monitoring | Health endpoint only | Health + logs | Prometheus/Grafana/SLO |

---

## Tier 1 — Shared Hosting Constraints

| Constraint | Implication |
|-----------|-------------|
| **No SSH** | Install, migrations, updates must all work from a browser |
| No Node.js runtime | React assets must be built before deployment |
| No npm | No build step on server |
| No Docker | Traditional LAMP stack |
| No Redis | File-based caching |
| No Composer on server | `vendor/` must ship inside the package |
| No long-running workers | Synchronous processing or cron-based queues |
| Cron available | Scheduled tasks (backup, cleanup, license verification) |
| PHP + MySQL | Laravel application |

**Consequence:** the shared package ships with `vendor/`, pre-cached config, and prebuilt assets, plus a web-based installer and web-based migration runner. See [Shared Hosting](./shared-hosting.md) and [No-SSH Deployment ADR](../decisions/README.md#adr-0011-no-ssh-deployment).

---

## Tier 2 — VPS/Cloud Constraints

| Constraint | Implication |
|-----------|-------------|
| SSH available | Full CLI toolchain (`artisan`, Composer, npm) |
| Root or sudo | Supervisor, systemd, Nginx configuration |
| Redis available | Queue + cache + rate limiting |
| Workers viable | Horizon or queue worker under Supervisor |
| Node.js available | Build assets on server *or* receive prebuilt |

---

## Tier 3 — Enterprise Constraints

| Constraint | Implication |
|-----------|-------------|
| Container runtime | 12-factor app, stateless web tier |
| Orchestrator (K8s) | Deployments, Services, Ingress, HPA |
| Managed data | RDS/Cloud SQL, managed Redis, object storage |
| CI/CD required | Build → test → image → deploy pipeline |
| Multi-region / HA | Read replicas, CDN, regional failover |
| Observability | Metrics, tracing, structured logs, alerting |

---

## Deployment Pipeline (Development → Production)

```
React/Laravel (dev)
    ↓
Git
    ↓
CI (GitHub Actions)
    ↓
Build (npm run build) ──→ Tier 2/3 image build (Docker)
    ↓                         ↓
Tier-appropriate package        Registry (ECR/GHCR)
    ↓                         ↓
Tier 1: ZIP (vendor/ + assets) Tier 2: ZIP + deploy script
Tier 3: Helm chart / manifest  Tier 3: K8s rollout
```

The generator selects the packaging step from `deployment_type` — see [Generation Process](../generator/process.md).

---

## Production Package Structure

Packages differ by tier. Tier 1 is self-contained (no tooling on server); Tiers 2/3 omit build-time artifacts and rely on server-side tooling.

### Tier 1 — Shared Hosting (`client-site-shared.zip`)

```
client-site/
├── app/
├── bootstrap/
│   └── cache/              # Pre-warmed by generator
├── config/                 # Pre-cached config
├── database/
│   └── migrations/
│       ├── platform/
│       └── client/
├── public/
│   ├── index.php
│   ├── platform-assets/    # Prebuilt React assets
│   └── .htaccess
├── resources/
├── routes/
├── storage/                # Empty skeleton (logs/framework/cache)
├── vendor/                 # Composer deps INCLUDED (no Composer on server)
├── platform.lock
├── .env.example
└── installer/              # Web installer (first-run wizard)
```

**Differences vs. Tier 2/3:** `vendor/` present, `node_modules/` absent, assets prebuilt, config pre-cached, installer included.

### Tier 2 — VPS/Cloud (`client-site-vps.zip` or Git tag)

```
client-site/
├── app/  bootstrap/  config/  database/  public/
├── resources/  routes/  storage/
├── vendor/                 # Included (Composer available but not required)
├── composer.json
├── package.json
├── deploy/
│   ├── provision.sh        # Server setup (Nginx, PHP, Redis, Supervisor)
│   └── update.sh           # `platform:update` CLI entrypoint
├── docker/                 # Optional compose for local parity
├── platform.lock
└── .env.example
```

### Tier 3 — Enterprise (container image + Helm chart)

```
deploy/helm/newser-site/
├── Chart.yaml
├── values.yaml             # replicas, resources, ingress, HPA
├── templates/
│   ├── deployment.yaml
│   ├── service.yaml
│   ├── ingress.yaml
│   ├── cronjob.yaml        # scheduler
│   ├── job-migration.yaml  # pre-upgrade migration Job
│   └── hpa.yaml
Dockerfile                  # multi-stage: node build → php-fpm runtime
```

---

## Pre-Deployment Checklist (by Tier)

| Step | Tier 1 | Tier 2 | Tier 3 |
|------|--------|--------|--------|
| React assets built | ✅ at generate time | ✅ at generate or on server | ✅ in image build |
| Laravel optimized | ✅ at generate time | ✅ on deploy | ✅ in image build |
| `.env` / `APP_KEY` | Web installer | `cp .env.example .env` + `key:generate` | Secrets manager |
| DB migrations | Web migration runner | `php artisan migrate` | K8s Job (pre-upgrade) |
| SSL certificate | cPanel/auto (Let's Encrypt) | Certbot / acme.sh | cert-manager |
| Cron | cPanel cron or cron-job.org | system cron | K8s CronJob |
| Queue workers | N/A (sync + cron) | Supervisor + Horizon | K8s Deployment + HPA |
| Health check | ✅ `GET /api/health` | ✅ | ✅ + probes (liveness/readiness) |
| Backup schedule | ✅ web panel | ✅ cron | ✅ Velero / managed snapshots |

---

## Related Documents

- [Shared Hosting — No SSH (Tier 1)](./shared-hosting.md)
- [VPS/Cloud (Tier 2)](./vps-cloud.md)
- [Enterprise (Tier 3)](./enterprise.md)
- [Update System](./update-system.md)
- [Generator Overview](../generator/overview.md)
- [Generation Process](../generator/process.md)
- [Platform Lock](../generator/platform-lock.md)
