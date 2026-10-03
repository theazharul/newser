# Component System

**Source:** §9-14 (plan.txt)

---

## Overview

Reusable components are the most important assets of the platform. Each component is self-contained with its own schema, data provider, renderer, and styles.

---

## Component Package Structure

```
packages/news-components/news-grid/
├── NewsGrid.tsx              # React component
├── schema.ts                 # Configuration schema
├── types.ts                  # TypeScript interfaces
├── data.ts                   # Data provider
├── styles.css                # Component styles
├── index.ts                  # Public exports
└── tests/
    ├── NewsGrid.test.tsx     # Unit tests
    └── NewsGrid.e2e.tsx      # E2E tests
```

---

## Component Registry

```typescript
// packages/news-components/src/registry.ts
import { Hero } from './hero';
import { BreakingNews } from './breaking-news';
import { NewsGrid } from './news-grid';
import { NewsList } from './news-list';
import { NewsCard } from './news-card';
import { LatestNews } from './latest-news';
import { PopularNews } from './popular-news';
import { FeaturedNews } from './featured-news';
import { CategorySection } from './category-section';
import { VideoGrid } from './video-grid';
import { PhotoGallery } from './photo-gallery';
import { Advertisement } from './advertisement';
import { Newsletter } from './newsletter';
import { SocialLinks } from './social-links';
import { AuthorBox } from './author-box';
import { RelatedPosts } from './related-posts';
import { Pagination } from './pagination';

export const registry = {
  hero: Hero,
  'breaking-news': BreakingNews,
  'news-grid': NewsGrid,
  'news-list': NewsList,
  'news-card': NewsCard,
  'latest-news': LatestNews,
  'popular-news': PopularNews,
  'featured-news': FeaturedNews,
  'category-section': CategorySection,
  'video-grid': VideoGrid,
  'photo-gallery': PhotoGallery,
  advertisement: Advertisement,
  newsletter: Newsletter,
  'social-links': SocialLinks,
  'author-box': AuthorBox,
  'related-posts': RelatedPosts,
  pagination: Pagination,
};
```

**Rule:** The builder never hard-codes components. It discovers them from the registry.

---

## Component Schema

Every component defines its configuration schema:

```typescript
// packages/news-components/news-grid/schema.ts
import { z } from 'zod';

export const NewsGridSchema = z.object({
  title: z.string().optional(),
  category: z.string().optional(),
  columns: z.union([z.literal(2), z.literal(3), z.literal(4)]).default(3),
  limit: z.number().min(1).max(50).default(8),
});

export type NewsGridConfig = z.infer<typeof NewsGridSchema>;
```

### Schema Field Types

| Type | Description | Example |
|------|-------------|---------|
| `text` | Single-line text | `"Sports"` |
| `textarea` | Multi-line text | `"Lorem ipsum..."` |
| `number` | Numeric value | `8` |
| `select` | Dropdown | `[2, 3, 4]` |
| `boolean` | Toggle | `true` / `false` |
| `color` | Color picker | `"#C00000"` |
| `category` | Category reference | `"sports"` |
| `image` | Media reference | `123` |

---

## Component Configuration

Stored as data, not code:

```json
{
  "component": "news-grid",
  "version": "2.1.0",
  "props": {
    "title": "Sports",
    "category": "sports",
    "limit": 8,
    "columns": 4
  }
}
```

**Rule:** Changing a component's configuration does not require modifying PHP or React source code.

---

## Data Provider Contract

```typescript
// packages/news-components/news-grid/data.ts
import { NewsGridConfig } from './types';
import { NewsItem } from '@news/types';

interface DataProvider<T> {
  fetch(config: NewsGridConfig): Promise<{
    data: T[];
    error: string | null;
    loading: boolean;
  }>;
}

export const NewsGridProvider: DataProvider<NewsItem> = {
  async fetch(config) {
    const params = new URLSearchParams();
    if (config.category) params.set('category', config.category);
    params.set('limit', String(config.limit));

    const response = await fetch(`/api/v1/posts?${params}`);
    const json = await response.json();

    return {
      data: json.data,
      error: json.error,
      loading: false,
    };
  },
};
```

---

## Component Props Contract

```typescript
// packages/news-components/news-grid/types.ts
import { NewsItem } from '@news/types';

export interface NewsGridProps {
  title?: string;
  category?: string;
  columns: 2 | 3 | 4;
  limit: number;
  items: NewsItem[];
}
```

---

## Initial Components

| Component | Description | Data Source |
|-----------|-------------|-------------|
| Hero | Large featured article | API |
| BreakingNews | Breaking news ticker | API |
| NewsCard | Single news card | API |
| NewsGrid | Grid of news cards | API |
| NewsList | List of news items | API |
| LatestNews | Latest posts | API |
| PopularNews | Popular posts | API |
| FeaturedNews | Featured posts | API |
| CategorySection | Category with posts | API |
| VideoGrid | Video gallery | API |
| PhotoGallery | Photo gallery | API |
| Advertisement | Ad placement | Config |
| Newsletter | Newsletter signup | API |
| SocialLinks | Social media links | Config |
| AuthorBox | Author information | API |
| RelatedPosts | Related articles | API |
| Pagination | Page navigation | API |

---

## Component Versioning

Each component has a version in its schema:

```json
{
  "name": "news-grid",
  "version": "2.1.0",
  "fields": { ... }
}
```

**Semver:**
- MAJOR: breaking changes
- MINOR: backward-compatible features
- PATCH: bug fixes

---

## Related Documents

- [Frontend Overview](./overview.md)
- [Theme System](./theme-system.md)
- [Builder](./builder.md)
- [Data Flow](../architecture/data-flow.md)
- [Gap Analysis](../gap-analysis.md) — GAP-005 (override resolution)
