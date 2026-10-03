# Core Entities

**Source:** §6, §7 (plan.txt)

---

## Entity Relationship Diagram

```
Users ──┬── Roles ──┬── Permissions
        │           │
        ├── Authors ──┬── Posts ──┬── Categories
        │             │           ├── Tags
        │             │           ├── Media (featured_image)
        │             │           └── SEO metadata
        │             │
        │             └── Posts (authored)
        │
        └── Clients ──┬── Projects ──┬── Domains
                      │              ├── Licenses
                      │              ├── Theme
                      │              ├── Configuration
                      │              └── platform.lock
                      │
                      └── Deployments ──┬── Updates
                                       ├── Backups
                                       └── Audit Logs
```

---

## User Management

### User
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | |
| email | string | unique |
| password | string | hashed |
| role_id | FK | → Roles |
| created_at | timestamp | |
| updated_at | timestamp | |

### Role
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | admin, editor, author, viewer |
| description | text | |

### Permission
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | posts.create, posts.edit, etc. |
| description | text | |

### Role-Permission (pivot)
| Field | Type |
|-------|------|
| role_id | FK |
| permission_id | FK |

---

## Client Management

### Client
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | |
| email | string | |
| phone | string | |
| address | text | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Project
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| client_id | FK | → Clients |
| name | string | |
| domain | string | |
| theme | string | |
| configuration | json | |
| status | string | draft, active, suspended |
| platform_version | string | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Domain
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| project_id | FK | → Projects |
| domain | string | |
| is_primary | boolean | |
| verified | boolean | |

### License
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| client_id | FK | → Clients |
| site_id | FK | → Projects |
| domain | string | |
| key | string | unique |
| status | string | active, expired, revoked |
| expires_at | timestamp | |
| platform_version | string | |
| features | json | |

---

## Content Entities

### Post
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| title | string | |
| slug | string | unique |
| excerpt | text | |
| body | longtext | |
| status | string | draft, scheduled, published, archived |
| author_id | FK | → Authors |
| category_id | FK | → Categories |
| featured_image_id | FK | → Media |
| published_at | timestamp | nullable |
| seo_title | string | |
| seo_description | string | |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:**
- `posts(status, published_at)` — for listing published posts
- `posts(category_id, status)` — for category filtering
- `posts(slug)` — for slug lookup
- Full-text: `posts(title, excerpt, body)` — for search

### Category
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | |
| slug | string | unique |
| description | text | |
| parent_id | FK | → Categories (self-referential) |
| created_at | timestamp | |
| updated_at | timestamp | |

### Tag
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | |
| slug | string | unique |
| created_at | timestamp | |
| updated_at | timestamp | |

### Post-Tag (pivot)
| Field | Type |
|-------|------|
| post_id | FK |
| tag_id | FK |

### Author
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| user_id | FK | → Users |
| name | string | |
| bio | text | |
| avatar | FK | → Media |
| designation | string | |
| social_links | json | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Media Entity

### Media
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| file_name | string | |
| file_path | string | |
| mime_type | string | |
| size | bigint | bytes |
| width | int | nullable (images) |
| height | int | nullable (images) |
| alt_text | string | |
| caption | text | |
| credit | string | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Media Variant
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| media_id | FK | → Media |
| variant_name | string | thumbnail, small, medium, large, webp, avif |
| file_path | string | |
| width | int | |
| height | int | |
| size | int | |

---

## Structural Entities

### Page
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| title | string | |
| slug | string | |
| content | longtext | |
| status | string | |
| seo_title | string | |
| seo_description | string | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Menu
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| name | string | |
| items | json | nested structure |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## SEO Entities

### SEO Metadata (embedded in Post/Page/Category)
| Field | Type |
|-------|------|
| seo_title | string |
| seo_description | string |
| canonical_url | string |
| og_title | string |
| og_description | string |
| og_image | string |
| twitter_card | string |

---

## Operational Entities

### Deployment
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| project_id | FK | → Projects |
| version | string | |
| status | string | pending, success, failed |
| deployed_at | timestamp | |
| created_at | timestamp | |

### Update
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| project_id | FK | → Projects |
| from_version | string | |
| to_version | string | |
| status | string | pending, success, failed, rolled_back |
| created_at | timestamp | |

### Backup
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| project_id | FK | → Projects |
| type | string | database, files, full |
| file_path | string | |
| size | bigint | |
| created_at | timestamp | |

### Audit Log
| Field | Type | Notes |
|-------|------|-------|
| id | bigint | PK |
| user_id | FK | → Users |
| action | string | |
| entity_type | string | |
| entity_id | bigint | |
| changes | json | |
| created_at | timestamp | |

---

## Related Documents

- [Backend Overview](./overview.md)
- [Media System](./media-system.md)
- [API](./api.md)
- [Migrations](./migrations.md)
