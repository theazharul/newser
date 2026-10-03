# Golden Rules

**Source:** §51 (plan.txt)

---

## The 20 Golden Rules

| # | Rule | Rationale |
|---|------|-----------|
| 1 | Build the platform once | Avoid duplication |
| 2 | Never copy-paste the entire platform for every client | Avoid maintenance nightmare |
| 3 | Never allow clients to edit core platform code | Protect platform integrity |
| 4 | Separate content from presentation | Enable theme switching |
| 5 | Separate theme source from theme configuration | Enable safe updates |
| 6 | Separate client customization from platform code | Enable safe updates |
| 7 | Version every reusable component | Enable independent updates |
| 8 | Version every theme | Enable independent updates |
| 9 | Maintain a platform.lock for every generated site | Enable safe updates |
| 10 | Preview and production must use the same renderer | Prevent "preview ≠ production" |
| 11 | Never generate a complete project for every builder change | Performance |
| 12 | Generate only after the client approves the design | Avoid wasted generation |
| 13 | Backup before updates | Enable rollback |
| 14 | Never blindly overwrite client customization | Protect client work |
| 15 | Use migrations for database changes | Enable safe schema changes |
| 16 | Build React assets before shared-hosting deployment | No Node.js on prod |
| 17 | Keep public pages SEO-friendly | Business requirement |
| 18 | Start with one excellent theme | Focus over breadth |
| 19 | Build reusable components before building many themes | Reusability first |
| 20 | Add Rust only when it solves a real problem | Avoid premature optimization |

---

## Related Documents

- [Development Phases](./phases.md)
- [MVP Scope](./mvp.md)
- [Architecture Overview](../architecture/overview.md)
- [Gap Analysis](../gap-analysis.md)
