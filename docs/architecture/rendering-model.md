# Rendering Model

**Status:** 🔴 CRITICAL — ADR required before implementation
**Source:** §2, §35, §41 (plan.txt)
**Gap:** GAP-001

---

## Problem Statement

The plan requires:
- "Prefer SSR/SSG architecture for SEO-heavy production sites" (§2)
- "Do not turn the public news website into an unnecessarily heavy SPA" (§35)
- "Do NOT require Node.js runtime on production" (§2, §41)

These constraints are only compatible if "SSR" means **build-time prerender**, not request-time render. The plan never names a framework or decides the strategy.

---

## Constraints

| Constraint | Source | Implication |
|-----------|--------|-------------|
| SEO-friendly | §2, §34 | Must render meta tags, structured data, semantic HTML |
| No heavy SPA | §35 | Must have fast TTFB, server-rendered shell |
| No Node on prod | §2, §41 | No runtime SSR; only build-time prerender |
| Shared hosting | §41 | PHP + MySQL + Apache/Nginx + Cron only |
| Same renderer for preview + prod | §22, §51 | Renderer must be framework-agnostic |

---

## Proposed Model: Hybrid Rendering

### Architecture

```
Request → Laravel Route → Blade Template (shell) → React Islands (interactive)
```

### Layer 1: Server-Rendered Shell (Blade)
- Page layout, header, footer, navigation
- SEO meta tags (title, description, canonical, OpenGraph, Twitter Cards)
- Structured data (JSON-LD)
- Critical CSS inline
- Fast TTFB (< 200ms)

### Layer 2: React Islands (Client-Hydrated)
- Search bar
- Comment sections
- Newsletter signup
- Ad rotation
- Interactive widgets (polls, quizzes)
- Below-the-fold content

### Layer 3: Build-Time SSG (Optional)
- Article pages with known content at build time
- Category pages
- Author pages
- Sitemap.xml, RSS feed

---

## Component Rendering Strategy

| Component | Rendering | Reason |
|-----------|-----------|--------|
| Header | Blade (server) | SEO-critical, static |
| Footer | Blade (server) | SEO-critical, static |
| Navigation | Blade (server) | SEO-critical, static |
| Hero | Blade + React island | Mostly static, may have rotation |
| News Grid | Blade (server) | SEO-critical, data from API |
| News Card | Blade (server) | SEO-critical |
| Search | React island | Interactive |
| Comments | React island | Dynamic, user-generated |
| Newsletter | React island | Interactive form |
| Advertisement | React island | Dynamic rotation |
| Social Links | Blade (server) | Static |
| Author Box | Blade (server) | SEO-critical |
| Related Posts | Blade (server) | SEO-critical |
| Pagination | Blade (server) | SEO-critical |

---

## SEO Implementation

### Meta Tags (Blade)
```blade
<title>{{ $post->seo_title ?? $post->title }}</title>
<meta name="description" content="{{ $post->seo_description }}">
<link rel="canonical" href="{{ $post->canonical_url }}">
<meta property="og:title" content="{{ $post->seo_title }}">
<meta property="og:description" content="{{ $post->seo_description }}">
<meta property="og:image" content="{{ $post->featured_image_url }}">
<meta name="twitter:card" content="summary_large_image">
```

### Structured Data (JSON-LD)
```json
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "...",
  "author": { "@type": "Person", "name": "..." },
  "datePublished": "...",
  "image": "..."
}
```

### Sitemap & RSS
- `sitemap.xml` — generated at build time, lists all published posts
- `rss.xml` — generated at build time, last 20 posts

---

## Caching Strategy

| Layer | Tool | TTL |
|-------|------|-----|
| Page cache | Laravel file cache | 1 hour |
| HTTP cache | ETag + Cache-Control | 1 hour |
| Query cache | Laravel cache | 15 minutes |
| Full-page cache | Laravel file cache | 1 hour |

**Invalidation:** On post publish/update/delete → clear page cache for affected URLs.

---

## Decision Needed

**ADR-0001** must resolve:
1. Confirm hybrid rendering model (Blade shell + React islands)
2. Choose React hydration strategy (lazy vs. eager)
3. Define build-time SSG scope (which pages are prerendered)
4. Choose caching backend (file-based, no Redis)

---

## Related Documents

- [Architecture Overview](./overview.md)
- [Data Flow](./data-flow.md)
- [Gap Analysis](../gap-analysis.md) — GAP-001
- [Decisions](../decisions/README.md) — ADR-0001
