# Enterprise Deployment (Tier 3)

**Source:** §41-43 (plan.txt)
**Target:** Large clients requiring HA, SLAs, multi-region, and CI/CD

---

## When This Tier Applies

- Client requires high availability, autoscaling, or multi-region presence
- Client has (or wants) Kubernetes, container registry, and managed data services
- Contract includes SLA obligations (uptime, RPO/RTO)
- Enterprise procurement requires infrastructure-as-code and auditability

`platform.lock` records `"deployment_type": "enterprise"`.

---

## Architecture

```
                        ┌──────────── CDN (CloudFront/Cloudflare) ────────────┐
                        │                    │ static assets + edge cache      │
                        ▼                    ▼                                 │
                   ┌─────────┐        ┌────────────┐                           │
Clients ──────────▶│ Ingress │───────▶│  web pods  │ (HPA: 2–20 replicas)     │
                   │(NGINX/  │        │ php-fpm +  │                           │
                   │ Traefik)│        │ nginx side │                           │
                   └────┬────┘        └─────┬──────┘                           │
                        │                   │                                  │
              ┌─────────┴──────┐   ┌────────┴─────────┐                        │
              │  queue pods    │   │  scheduler pods   │ (CronJob pattern)     │
              │  (Horizon)     │   │  platform:* jobs  │                        │
              └────────┬───────┘   └────────┬──────────┘                        │
                       │                    │                                   │
      ┌────────────────┴────────────────────┴───────────────┐                   │
      │  Managed services:                                  │                   │
      │  • RDS/Cloud SQL (Multi-AZ, read replicas)          │                   │
      │  • ElastiCache / Memorystore (Redis)                │                   │
      │  • S3 / GCS / Blob (media, backups, asset bundles)  │                   │
      │  • Secrets Manager / Vault                          │                   │
      └─────────────────────────────────────────────────────┘                   │
```

**Key principle:** the application is identical to Tier 1/2. Only packaging, orchestration, and operations differ.

---

## Infrastructure Requirements

| Component | Specification |
|-----------|--------------|
| Kubernetes | 1.27+ (EKS / GKE / AKS / self-managed) |
| Nodes | ≥3 (2 AZ minimum), autoscaling node groups |
| PHP image | php-fpm 8.2+ multi-stage build |
| DB | Managed, Multi-AZ, PITR enabled |
| Redis | Managed, cluster mode for queue |
| Object storage | S3-compatible with lifecycle policies |
| Registry | ECR / GHCR / GCR with image scanning |
| Ingress | NGINX Ingress or Gateway API + cert-manager |
| Secrets | External Secrets Operator → Vault/SM |

---

## Container Image

Multi-stage `Dockerfile` (generated into the package):

```dockerfile
# ---- build stage ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# ---- runtime stage ----
FROM php:8.3-fpm-alpine
RUN docker-php-ext-install pdo_mysql opcache && \
    pecl install redis && docker-php-ext-enable redis
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/platform-assets ./public/platform-assets
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
```

CI builds, scans (Trivy), signs, and pushes: `registry.example.com/newser-site:<git-sha>`.

---

## Helm Chart

Generated at `deploy/helm/newser-site/`:

| Template | Purpose |
|----------|---------|
| `deployment.yaml` | web pods — probes, resources, rolling update strategy |
| `service.yaml` | ClusterIP for web |
| `ingress.yaml` | TLS, host rules, annotations |
| `cronjob.yaml` | Laravel scheduler (`schedule:run` every minute) |
| `job-migration.yaml` | `helm.sh/hook: pre-upgrade,pre-install` migration runner |
| `hpa.yaml` | CPU/memory autoscaling |
| `worker-deployment.yaml` | Horizon pods |
| `pdb.yaml` | PodDisruptionBudget (minAvailable: 1) |
| `servicemonitor.yaml` | Prometheus scraping (optional) |

`values.yaml` is parameterized per environment:

```yaml
replicaCount: 3
image:
  repository: registry.example.com/newser-site
  tag: "1.4.0"
ingress:
  className: nginx
  hosts: [{ host: example.com, paths: [{ path: /, pathType: Prefix }] }]
  tls: [{ secretName: example-tls }]
autoscaling:
  enabled: true
  minReplicas: 2
  maxReplicas: 20
resources:
  requests: { cpu: 100m, mi: 256Mi }
  limits: { cpu: 500m, mi: 512Mi }
redis:
  urlFrom: { secretRef: newser-redis, key: url }
db:
  urlFrom: { secretRef: newser-db, key: url }
```

---

## Deployment Flow

```bash
# First install
helm install my-site ./deploy/helm/newser-site \
  -f values-prod.yaml \
  --set image.tag=$(git rev-parse --short HEAD)

# Upgrade (migrations run automatically via pre-upgrade hook)
helm upgrade my-site ./deploy/helm/newser-site \
  -f values-prod.yaml \
  --set image.tag=$NEW_TAG

# Rollback
helm rollback my-site
```

### Release pipeline (GitOps)

```
git push → CI (test + build image + Trivy scan)
              ↓
       push image to registry
              ↓
       ArgoCD/Flux detects new tag (or CD job)
              ↓
       helm upgrade → pre-upgrade migration Job → rolling update
              ↓
       readiness probes gate traffic shift
              ↓
       post-deploy smoke tests (canary 10% → 100%)
```

---

## Migrations at Scale

Migrations run as a **K8s Job**, not inside web pods:

- `backoffLimit: 3`, `ttlSecondsAfterFinished`
- Acquires a DB advisory lock (`GET_LOCK`) to prevent concurrent runs
- Web pods never run migrations → no downtime from long DDL
- On failure: Job fails → `helm upgrade` fails → automatic rollback of image

Large-table migrations use `pt-online-schema-change` or `Laravel` batched updates (1000 rows/commit) to avoid lock storms.

---

## Zero-Downtime Updates

| Mechanism | Configuration |
|-----------|--------------|
| Rolling update | `maxUnavailable: 0`, `maxSurge: 25%` |
| Readiness probe | `GET /api/health` — gates traffic until app is live |
| Liveness probe | `GET /api/health/live` |
| Pre-stop sleep | `sleep 5` — lets in-flight requests drain |
| DB migration | pre-upgrade Job, backward-compatible schema only (expand → migrate → contract) |
| Asset swap | baked into image; old ReplicaSets retain previous assets for instant rollback |
| Rollback | `helm rollback` + image tag revert; RPO = last DB snapshot |

**Expand–contract rule:** every schema change ships in ≥2 releases (add nullable column → backfill → switch reads → drop old column) so old and new pods can coexist.

---

## Multi-Region / HA

| Concern | Approach |
|---------|----------|
| Availability | Multi-AZ DB, ≥2 web pods across AZs, PodDisruptionBudget |
| Multi-region active/passive | Regional clusters + Route53 failover / GeoDNS; DB cross-region read replica |
| Multi-region active/active | Single primary DB region + read replicas elsewhere; media replicated to regional buckets; cache invalidation via CDN |
| Failover target | RPO ≤ 5 min (PITR), RTO ≤ 15 min |
| Static assets | CDN edge caching, immutable asset filenames |
| Media | Global object storage + CDN signed URLs |

**Decision required:** active/passive is the MVP recommendation; active/active needs ADR (data residency + conflict resolution).

---

## Observability

| Layer | Tooling |
|-------|---------|
| Logs | stdout → Fluent Bit/Vector → Loki/CloudWatch/ELK (JSON structured) |
| Metrics | Prometheus + Grafana (app + node + kube-state) |
| Tracing | OpenTelemetry → Tempo/Jaeger |
| Alerting | Alertmanager → PagerDuty/Slack |
| SLO | 99.9% availability on `/api/health`; p95 TTFB < 500ms |
| Audit | Laravel audit log for admin actions → immutable store |

### Application instrumentation

- Structured JSON logs with `request_id` correlation
- `/api/health`, `/api/health/live`, `/api/health/ready`
- Metrics endpoint: request count, latency histogram, queue depth, failed jobs

---

## Security & Compliance

- Image scanning (Trivy) in CI; block deploy on critical CVEs
- Non-root container user
- NetworkPolicies: web → db/redis only; no egress except allowlist
- Secrets via External Secrets Operator; never in `values.yaml` or images
- TLS everywhere (cert-manager, cert rotation)
- WAF at ingress/CDN (Cloudflare WAF / AWS WAF)
- Rate limiting at ingress + Redis
- SOC2/ISO evidence: audit logs, change history via Git, immutable image tags

---

## Capability Matrix (Tier 3)

| Capability | Status |
|------------|--------|
| `has_ssh` | ✅ (or GitOps, no shell needed) |
| `has_queue` | ✅ (Redis + Horizon) |
| `has_redis` | ✅ managed |
| `has_node` | ✅ (CI only) |
| `has_workers` | ✅ (K8s Deployment) |
| `has_docker` | ✅ required |
| `has_orchestrator` | ✅ K8s |
| `has_autoscale` | ✅ HPA/VPA |
| `has_multi_region` | optional |
| `has_observability` | ✅ |

---

## Cost & Sizing (Reference)

| Profile | Nodes | DB | Monthly (approx.) |
|---------|-------|----|-------------------|
| Small site | 3 × small | db.t4g.medium Multi-AZ | $400–700 |
| High traffic | 3 × large + HPA to 20 | db.r6g.large + replica | $1,500–4,000 |
| Multi-region | 3 per region | multi-region replica | $5,000+ |

Right-sizing is per-client; the Helm chart exposes CPU/memory/replica knobs.

---

## Related Documents

- [Deployment Overview](./overview.md)
- [Shared Hosting (Tier 1)](./shared-hosting.md)
- [VPS/Cloud (Tier 2)](./vps-cloud.md)
- [Update System](./update-system.md)
- [Generator Process](../generator/process.md)
- [Platform Lock](../generator/platform-lock.md)
