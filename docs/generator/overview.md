# Generator Overview

**Source:** §39-40 (plan.txt)

---

## What the Generator Does

The generator creates a standalone, deployable client website from a project configuration. The generated site is a complete Laravel + React application that can be deployed to shared hosting.

---

## Generator Inputs

| Input | Description |
|-------|-------------|
| Site name | Client website name |
| Domain | Primary domain |
| Theme | Selected theme (e.g., modern) |
| Logo | Logo image |
| Colors | Color overrides |
| Enabled components | Which components to include |
| Homepage | Homepage section configuration |
| Features | Feature flags (comments, video, newsletter) |
| Deployment target | Shared hosting, VPS, enterprise |

---

## Generator Outputs

```
client-site/
├── app/
│   ├── Http/Controllers/    # Public controllers
│   ├── Models/              # Eloquent models
│   └── ...
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   │   ├── platform/        # Platform-controlled
│   │   └── client/          # Client-controlled
│   └── seeders/
├── public/
│   ├── index.php
│   ├── assets/              # Compiled React assets
│   └── platform-assets/     # Platform-managed assets
├── resources/
│   ├── js/                  # React runtime
│   ├── views/               # Blade templates
│   └── css/
├── routes/
├── storage/
├── composer.json
├── package.json
├── platform.lock
└── .env.example
```

---

## Generator Tech Stack

- **MVP:** Laravel + Node.js (PHP + npm)
- **Future:** Rust CLI (Phase 9)
- **Future:** Tier 2/3 packaging — VPS + Enterprise (Phase 10, GAP-010)

---

## Generator Versioning

The generator itself is versioned (§30). The `platform.lock` file records which generator version was used.

---

## Related Documents

- [Generation Process](./process.md)
- [Platform Lock](./platform-lock.md)
- [Deployment Overview](../deployment/overview.md)
- [Update System](../deployment/update-system.md)
