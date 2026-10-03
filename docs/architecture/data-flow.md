# Data Flow — Content/Presentation Separation

**Source:** §14 (plan.txt)

---

## Core Rule

```
Database → API / Data Provider → Component Data → React Component → Theme → HTML
```

**Never mix database queries directly into visual components.**

---

## Data Flow Diagram

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐     ┌──────────────┐     ┌────────┐
│  Database   │ ──→ │  API / Data  │ ──→ │  Component  │ ──→ │   React      │ ──→ │  HTML  │
│  (MySQL)    │     │  Provider    │     │  Data       │     │   Component  │     │        │
└─────────────┘     └──────────────┘     └─────────────┘     └──────────────┘     └────────┘
                           │                    │                    │
                           │                    │                    │
                    ┌──────┴──────┐      ┌─────┴─────┐       ┌─────┴─────┐
                    │  Laravel    │      │  Typed    │       │  Theme    │
                    │  Models     │      │  Interface│       │  Tokens   │
                    └─────────────┘      └───────────┘       └───────────┘
```

---

## Data Provider Contract

Every component receives data through a typed interface:

```typescript
interface DataProvider<T> {
  fetch(config: ComponentConfig): Promise<{
    data: T[];
    error: string | null;
    loading: boolean;
  }>;
}
```

### Example: NewsGrid Data Provider

```typescript
const NewsGridProvider: DataProvider<NewsItem> = {
  async fetch(config) {
    const response = await fetch(`/api/v1/posts?category=${config.category}&limit=${config.limit}`);
    const json = await response.json();
    return {
      data: json.data,
      error: json.error,
      loading: false
    };
  }
};
```

---

## Component Data Shape

Components receive plain data objects, not Eloquent models:

```typescript
interface NewsItem {
  id: number;
  title: string;
  slug: string;
  excerpt: string;
  featured_image_url: string;
  published_at: string;
  author_name: string;
  category_name: string;
}
```

---

## API Response Format

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200
  },
  "error": null
}
```

---

## Separation of Concerns

| Layer | Responsibility | Location |
|-------|---------------|----------|
| Database | Store data | MySQL |
| API | Fetch + format data | Laravel |
| Data Provider | Fetch + cache | `packages/news-renderer/` |
| Component Data | Typed interface | `packages/news-types/` |
| React Component | Render UI | `packages/news-components/` |
| Theme | Style + layout | `packages/news-themes/` |

---

## Anti-Patterns

❌ **Don't do this:**
```typescript
// Component directly queries database
const posts = DB::table('posts')->where('category', $category)->get();
return <NewsGrid posts={posts} />;
```

✅ **Do this:**
```typescript
// Component receives data from provider
const { data: posts } = useNewsGridData(config);
return <NewsGrid items={posts} />;
```

---

## Related Documents

- [Architecture Overview](./overview.md)
- [Component System](../frontend/component-system.md)
- [Backend API](../backend/api.md)
