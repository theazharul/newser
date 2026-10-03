# Architecture Overview

**Source:** §3, §52 (plan.txt)

---

## System Diagram

```
                    NEWS PLATFORM
                         │
             ┌───────────┴───────────┐
             │                       │
       React Admin              React Builder
             │                       │
             └───────────┬───────────┘
                         │
                    Laravel API
                         │
                      MySQL
                         │
             ┌───────────┴───────────┐
             │                       │
       Project Management       Content Management
             │
             ↓
       Theme + Components
             │
             ↓
       Preview Renderer
             │
             ↓
       Project Configuration
             │
             ↓
       Site Generator
             │
             ↓
       Standalone Client Site
```

---

## Core Principles

1. **Build the platform once** — never copy-paste for each client
2. **Separate content from presentation** — themes control appearance, never business logic
3. **Separate theme source from theme configuration** — clients modify config, not source
4. **Separate client customization from platform code** — 4-level model
5. **Version everything** — platform, theme, component, generator
6. **Preview = Production** — same renderer for both
7. **Backup before updates** — always
8. **Never overwrite client customization** — updates modify only platform-controlled files

---

## High-Level Components

### 1. Central Platform
- **Laravel API** — authentication, users, roles, permissions, clients, projects, posts, categories, tags, authors, media, pages, menus, SEO, ads, configuration, theme registry, component registry, versions, licenses, deployment info, update management, audit logs
- **React Admin** — platform administration (manage clients, projects, themes, components)
- **React Builder** — visual website builder (drag/drop, configure, preview)

### 2. Generated Client Site
- **Laravel** — API + Admin + React Runtime
- **React Runtime** — theme + components + configuration
- **platform.lock** — version manifest

### 3. Shared Packages
- `news-types` — shared TypeScript types
- `news-components` — reusable React components
- `news-theme-engine` — theme resolution + token system
- `news-builder` — builder state management
- `news-renderer` — preview/production renderer
- `news-config` — configuration schema + validation
- `news-themes` — theme packages (modern, classic, magazine)

---

## Data Flow

```
Database → API / Data Provider → Component Data → React Component → Theme → HTML
```

**Rule:** Never mix database queries directly into visual components. Components receive plain data objects.

---

## Deployment Targets

| Mode | Infrastructure | Use Case |
|------|---------------|----------|
| Shared Hosting | PHP + MySQL + Apache/Nginx + Cron | MVP, small clients |
| VPS | Laravel + React + MySQL + Redis + Queue | Medium clients |
| Enterprise | Docker + DB + Redis + Queue + Storage + Monitoring | Large clients |

**Constraint:** The application architecture must remain identical across all modes.

---

## Related Documents

- [Rendering Model](./rendering-model.md) — how pages are rendered
- [Data Flow](./data-flow.md) — content/presentation separation
- [Monorepo Structure](./monorepo.md) — repository layout
- [Gap Analysis](../gap-analysis.md) — critical blockers
