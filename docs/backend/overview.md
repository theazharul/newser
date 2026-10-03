# Backend Overview

**Source:** §5 (plan.txt)

---

## Laravel Responsibilities

The Laravel backend is the central platform's brain. It manages:

| Category | Entities |
|----------|----------|
| **User Management** | Users, Roles, Permissions |
| **Client Management** | Clients, Projects, Domains, Licenses |
| **Content** | Posts, Categories, Tags, Authors |
| **Media** | Media files, variants, metadata |
| **Structure** | Pages, Menus |
| **SEO** | Meta tags, structured data, sitemap, RSS |
| **Monetization** | Advertisements |
| **Configuration** | Project configurations, theme registry, component registry |
| **Versioning** | Versions, platform.lock |
| **Operations** | Deployments, Updates, Backups, Audit Logs |

---

## Architecture Pattern

```
HTTP Request → Controller → Service/Repository → Model → Database
                    ↓
              Resource (API response)
                    ↓
              Form Request (validation)
```

---

## Key Design Decisions

### 1. API-First
All functionality is exposed via REST API. The React admin and builder consume the same API.

### 2. Multi-Tenancy
- **Platform level:** manages multiple clients
- **Client level:** each client has isolated projects, content, and configuration
- **Isolation:** database-level (separate tables per client) or schema-level (shared tables with `client_id`)

### 3. Queue Strategy
- **MVP:** synchronous processing (no workers)
- **Bulk operations:** `php artisan queue:work --once` via cron
- **Driver:** database (no Redis required)

### 4. Caching
- **Driver:** file-based (no Redis required)
- **TTL:** 15 minutes for queries, 1 hour for pages
- **Invalidation:** on model save/delete

---

## Directory Structure

```
apps/platform/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── PostController.php
│   │   │   │   ├── CategoryController.php
│   │   │   │   ├── MediaController.php
│   │   │   │   ├── ProjectController.php
│   │   │   │   ├── ThemeController.php
│   │   │   │   ├── ComponentController.php
│   │   │   │   └── LicenseController.php
│   │   │   └── Web/
│   │   │       └── PageController.php
│   │   ├── Requests/
│   │   │   ├── StorePostRequest.php
│   │   │   ├── UpdatePostRequest.php
│   │   │   └── ...
│   │   ├── Resources/
│   │   │   ├── PostResource.php
│   │   │   ├── CategoryResource.php
│   │   │   └── ...
│   │   └── Middleware/
│   │       ├── Authenticate.php
│   │       ├── CheckPermission.php
│   │       └── ...
│   ├── Models/
│   │   ├── User.php
│   │   ├── Post.php
│   │   ├── Category.php
│   │   ├── Tag.php
│   │   ├── Author.php
│   │   ├── Media.php
│   │   ├── Page.php
│   │   ├── Menu.php
│   │   ├── Client.php
│   │   ├── Project.php
│   │   ├── License.php
│   │   ├── Theme.php
│   │   ├── Component.php
│   │   ├── Advertisement.php
│   │   ├── Deployment.php
│   │   ├── Update.php
│   │   ├── Backup.php
│   │   └── AuditLog.php
│   ├── Services/
│   │   ├── PostService.php
│   │   ├── MediaService.php
│   │   ├── ThemeService.php
│   │   ├── ComponentService.php
│   │   ├── LicenseService.php
│   │   ├── DeploymentService.php
│   │   └── UpdateService.php
│   ├── Repositories/
│   │   ├── PostRepository.php
│   │   ├── CategoryRepository.php
│   │   └── ...
│   ├── Policies/
│   │   ├── PostPolicy.php
│   │   ├── ProjectPolicy.php
│   │   └── ...
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── AuthServiceProvider.php
│       └── RouteServiceProvider.php
├── config/
├── database/
│   ├── migrations/
│   │   ├── platform/              # Platform-controlled
│   │   └── client/                # Client-controlled
│   └── seeders/
├── routes/
│   ├── api.php
│   ├── web.php
│   ├── admin.php
│   └── console.php
├── resources/
│   ├── views/                     # Blade templates
│   └── js/                        # React runtime
├── storage/
├── composer.json
├── package.json
└── .env.example
```

---

## Related Documents

- [Entities](./entities.md) — data models
- [Media System](./media-system.md) — media handling
- [API](./api.md) — API endpoints
- [Migrations](./migrations.md) — migration strategy
- [Architecture Overview](../architecture/overview.md)
