# Theme System

**Source:** §15-18 (plan.txt)

---

## Overview

Themes are independent packages that control presentation. They must NOT contain core business logic.

---

## Theme Package Structure

```
packages/news-themes/modern/
├── src/
│   ├── layouts/
│   │   ├── DefaultLayout.tsx
│   │   └── ArticleLayout.tsx
│   ├── components/
│   │   ├── Header.tsx
│   │   ├── Footer.tsx
│   │   ├── Navigation.tsx
│   │   └── ...
│   ├── tokens/
│   │   ├── colors.ts
│   │   ├── typography.ts
│   │   └── spacing.ts
│   ├── styles/
│   │   └── theme.css
│   └── index.ts
├── package.json
└── tsconfig.json
```

---

## Theme Rule

| Layer | Responsibility | Location |
|-------|---------------|----------|
| **Core** | Posts, Categories, Users, Media, SEO, Permissions | Laravel + `news-types` |
| **Theme** | Layout, Colors, Typography, Header, Footer, Component appearance | `news-themes/` |

**Themes control presentation. Themes must NOT contain core business logic.**

---

## Design Tokens

Each theme exposes design tokens:

```typescript
// packages/news-themes/modern/src/tokens/colors.ts
export const colors = {
  primary: '#C00000',
  secondary: '#222222',
  background: '#FFFFFF',
  text: '#111111',
  muted: '#777777',
  border: '#DDDDDD',
};

// packages/news-themes/modern/src/tokens/typography.ts
export const typography = {
  heading: 'Inter, sans-serif',
  body: 'Source Sans Pro, sans-serif',
};

// packages/news-themes/modern/src/tokens/spacing.ts
export const spacing = {
  xs: '4px',
  sm: '8px',
  md: '16px',
  lg: '24px',
  xl: '32px',
};
```

---

## Theme Configuration

```json
{
  "colors": {
    "primary": "#C00000",
    "secondary": "#222222",
    "background": "#FFFFFF",
    "text": "#111111",
    "muted": "#777777",
    "border": "#DDDDDD"
  },
  "typography": {
    "heading": "Inter",
    "body": "Source Sans Pro"
  },
  "layout": {
    "container": "1200px",
    "sidebar": "right"
  }
}
```

---

## Theme Overrides

### 3-Layer Resolution Order

```
Layer 1: Client Override (highest priority)
    ↓
Layer 2: Theme Default Component
    ↓
Layer 3: Platform Component Registry (lowest priority)
```

### Override Format

Client overrides are stored as **patch files** (not full copies):

```json
{
  "component": "header",
  "base_version": "1.2.0",
  "override": {
    "props": {
      "logo": "/custom-logo.png",
      "sticky": true
    }
  }
}
```

### Override Resolution

When the platform updates a component:
1. If client has no override → apply update cleanly
2. If client has override → run 3-way merge (base_version + client_patch + new_version)
3. Conflict → flag for manual resolution, keep client override

---

## Theme Inheritance

```
Modern Theme
    ├── Header
    ├── Hero
    ├── NewsCard
    ├── NewsGrid
    └── Footer

Client Extension
    ├── Header override
    └── NewsCard override
```

**The client does not copy the entire theme.** Therefore:
- Theme update → Client receives update → Client overrides remain

---

## Theme Registry

```typescript
// packages/news-theme-engine/src/registry.ts
import { ModernTheme } from '@news/themes/modern';
import { ClassicTheme } from '@news/themes/classic';
import { MagazineTheme } from '@news/themes/magazine';

export const themeRegistry = {
  modern: ModernTheme,
  classic: ClassicTheme,
  magazine: MagazineTheme,
};
```

---

## Available Themes

| Theme | Description | Status |
|-------|-------------|--------|
| Modern | Clean, minimal, card-based | MVP |
| Classic | Traditional newspaper layout | Phase 2 |
| Magazine | Visual, image-heavy | Phase 2 |

---

## Related Documents

- [Frontend Overview](./overview.md)
- [Component System](./component-system.md)
- [Builder](./builder.md)
- [Client Customization](../client-customization/overview.md)
- [Gap Analysis](../gap-analysis.md) — GAP-005 (override resolution)
