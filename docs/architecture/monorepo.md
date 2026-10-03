# Monorepo Structure

**Source:** §4 (plan.txt)

---

## Repository Layout

```
news-platform/
├── apps/
│   ├── platform/                  # Laravel backend (central platform)
│   │   ├── app/
│   │   ├── bootstrap/
│   │   ├── config/
│   │   ├── database/
│   │   ├── public/
│   │   ├── resources/
│   │   ├── routes/
│   │   ├── storage/
│   │   ├── composer.json
│   │   └── package.json
│   │
│   ├── admin/                     # React admin application
│   │   ├── src/
│   │   ├── public/
│   │   ├── index.html
│   │   ├── package.json
│   │   ├── tsconfig.json
│   │   └── vite.config.ts
│   │
│   ├── builder/                   # React visual builder
│   │   ├── src/
│   │   ├── public/
│   │   ├── index.html
│   │   ├── package.json
│   │   ├── tsconfig.json
│   │   └── vite.config.ts
│   │
│   └── client-runtime/              # React public website runtime
│       ├── src/
│       ├── public/
│       ├── index.html
│       ├── package.json
│       ├── tsconfig.json
│       └── vite.config.ts
│
├── packages/
│   ├── ui/                        # Shared UI primitives
│   │   ├── src/
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── news-types/                # Shared TypeScript types
│   │   ├── src/
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── news-components/           # Reusable React components
│   │   ├── src/
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── news-theme-engine/         # Theme resolution + token system
│   │   ├── src/
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── news-builder/              # Builder state management
│   │   ├── src/
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── news-renderer/             # Preview/production renderer
│   │   ├── src/
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── news-config/               # Configuration schema + validation
│   │   ├── src/
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   └── news-themes/               # Theme packages
│       ├── modern/
│       │   ├── src/
│       │   ├── package.json
│       │   └── tsconfig.json
│       ├── classic/
│       │   ├── src/
│       │   ├── package.json
│       │   └── tsconfig.json
│       └── magazine/
│           ├── src/
│           ├── package.json
│           └── tsconfig.json
│
├── generator/                     # Site generator
│   ├── src/
│   ├── package.json
│   └── tsconfig.json
│
├── docs/                          # Documentation (this directory)
│
├── tests/                         # E2E tests
│   ├── acceptance/
│   └── unit/
│
├── .github/
│   └── workflows/                 # CI/CD
│       ├── test.yml
│       └── build.yml
│
├── package.json                   # Root package.json (workspaces)
├── pnpm-workspace.yaml            # pnpm workspace config
├── tsconfig.base.json             # Shared TypeScript config
├── .editorconfig
├── .gitignore
└── README.md
```

---

## Package Manager

**pnpm** — fast, disk-efficient, strict dependency resolution.

```yaml
# pnpm-workspace.yaml
packages:
  - 'apps/*'
  - 'packages/*'
  - 'packages/news-themes/*'
  - 'generator'
```

---

## Shared TypeScript Config

```json
// tsconfig.base.json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "forceConsistentCasingInFileNames": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "react-jsx",
    "baseUrl": ".",
    "paths": {
      "@news/types": ["packages/news-types/src"],
      "@news/components": ["packages/news-components/src"],
      "@news/theme-engine": ["packages/news-theme-engine/src"],
      "@news/builder": ["packages/news-builder/src"],
      "@news/renderer": ["packages/news-renderer/src"],
      "@news/config": ["packages/news-config/src"]
    }
  }
}
```

---

## Dependency Graph

```
apps/admin → packages/ui, packages/news-types
apps/builder → packages/ui, packages/news-types, packages/news-builder, packages/news-components
apps/client-runtime → packages/ui, packages/news-types, packages/news-components, packages/news-theme-engine, packages/news-renderer
packages/news-components → packages/news-types, packages/ui
packages/news-renderer → packages/news-types, packages/news-components, packages/news-theme-engine
packages/news-theme-engine → packages/news-types
generator → packages/news-config, packages/news-types
```

---

## Versioning Strategy

- **Platform:** semver (MAJOR.MINOR.PATCH)
- **Packages:** independent semver
- **Lock file:** `pnpm-lock.yaml` committed to repo
- **Changesets:** use changesets for versioning + changelog

---

## Related Documents

- [Architecture Overview](./overview.md)
- [Development Phases](../development/phases.md)
