# MVP Scope

**Source:** §49 (plan.txt)

---

## MVP Definition

The MVP proves the complete loop: Build → Preview → Customize → Generate → Deploy.

---

## MVP Backend

| Feature | Description |
|---------|-------------|
| Laravel | Backend framework |
| MySQL | Database |
| Authentication | Sanctum |
| Posts | CRUD + publishing |
| Categories | Hierarchical |
| Authors | Author profiles |
| Media | Upload + variants |
| SEO | Meta tags + structured data |

---

## MVP Frontend

| Feature | Description |
|---------|-------------|
| React | UI framework |
| TypeScript | Type safety |
| Tailwind | Styling |
| Vite | Build tool |

---

## MVP Public Pages

| Page | Description |
|------|-------------|
| Homepage | Configurable sections |
| Article | Single post |
| Category | Category listing |
| Search | Search results |
| Author | Author profile |

---

## MVP Components

| Component | Description |
|-----------|-------------|
| Hero | Featured article |
| BreakingNews | News ticker |
| NewsCard | Single card |
| NewsGrid | Card grid |
| NewsList | Item list |
| LatestNews | Recent posts |
| Advertisement | Ad placement |

---

## MVP Theme

| Theme | Description |
|-------|-------------|
| Modern | Clean, minimal, card-based |

---

## MVP Builder

| Feature | Description |
|---------|-------------|
| Project creation | Create new project |
| Theme selection | Select Modern theme |
| Component selection | Add/remove components |
| Drag/reorder | Reorder sections |
| Component settings | Configure components |
| Theme settings | Change colors, fonts |
| Live preview | Real-time preview |

---

## MVP Generator

| Feature | Description |
|---------|-------------|
| Standalone Laravel + React project | Generated site |
| ZIP package | Downloadable |
| Shared-hosting compatible | Deploys anywhere |

---

## MVP Versioning

| Feature | Description |
|---------|-------------|
| platform.lock | Version manifest |

---

## MVP Success Criteria

The MVP is successful when you can do this (§55):

1. Create a project
2. Select Modern theme
3. Add Hero
4. Add News Grid
5. Configure News Grid
6. Reorder sections
7. Change colors
8. See live preview
9. Approve design
10. Generate project
11. Download deployment package
12. Deploy to shared hosting
13. Add real client content
14. Release a component update
15. Upgrade the client site
16. Verify client content/customizations remain intact

---

## Explicitly NOT in MVP

- Microservices
- Kubernetes
- Elasticsearch
- AI page builder
- Real-time collaboration
- Rust backend
- Dozens of themes
- Dozens of components
- Complex visual editor
- Multi-region infrastructure
- Unnecessary Redis dependency
- Unnecessary Docker dependency
- Comments system
- Multi-language support
- Analytics/consent

---

## Related Documents

- [Development Phases](./phases.md)
- [Golden Rules](./golden-rules.md)
- [Gap Analysis](../gap-analysis.md)
