# Frontend Overview

**Source:** §2, §9 (plan.txt)

---

## Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Framework | React | 18+ |
| Language | TypeScript | 5+ |
| Build | Vite | 5+ |
| Styling | Tailwind CSS | 3+ |
| Routing | React Router | 6+ |
| State | Zustand | 4+ |
| Testing | Vitest | 1+ |
| E2E | Playwright | 1+ |

---

## Applications

### 1. Admin (`apps/admin/`)
Platform administration interface. Manages clients, projects, themes, components, licenses.

**Features:**
- Dashboard (stats, recent activity)
- Client management
- Project management
- Theme registry
- Component registry
- License management
- User/role management
- Audit logs

### 2. Builder (`apps/builder/`)
Visual website builder. Drag-and-drop interface for designing news sites.

**Features:**
- Component palette (left sidebar)
- Live preview (center canvas)
- Settings panel (right sidebar)
- Drag-and-drop reordering
- Theme selector
- Color/typography customization
- Demo content toggle
- Generate button

### 3. Client Runtime (`apps/client-runtime/`)
The generated public website runtime. Shipped to client sites.

**Features:**
- Homepage (configurable sections)
- Article page
- Category page
- Author page
- Search page
- Static pages
- Header/footer/navigation
- SEO meta tags
- Structured data

---

## Shared Packages

| Package | Purpose |
|---------|---------|
| `news-types` | Shared TypeScript interfaces |
| `news-components` | Reusable React components |
| `news-theme-engine` | Theme resolution + token system |
| `news-builder` | Builder state management |
| `news-renderer` | Preview/production renderer |
| `news-config` | Configuration schema + validation |
| `news-themes` | Theme packages (modern, classic, magazine) |

---

## Component Architecture

```
Component = Schema + Data Provider + Renderer + Styles
```

Each component is self-contained:
- `Component.tsx` — React component
- `schema.ts` — configuration schema
- `types.ts` — TypeScript interfaces
- `data.ts` — data provider
- `styles.css` — component styles
- `tests/` — unit tests

---

## State Management (Zustand)

```typescript
// packages/news-builder/src/store.ts
interface BuilderState {
  project: Project;
  theme: Theme;
  sections: Section[];
  selectedSection: string | null;
  previewMode: boolean;

  // Actions
  addSection: (component: string) => void;
  removeSection: (id: string) => void;
  reorderSections: (from: number, to: number) => void;
  updateSection: (id: string, config: object) => void;
  setTheme: (theme: string) => void;
  togglePreview: () => void;
}
```

---

## Related Documents

- [Component System](./component-system.md)
- [Theme System](./theme-system.md)
- [Builder](./builder.md)
- [Architecture Overview](../architecture/overview.md)
- [Monorepo Structure](../architecture/monorepo.md)
