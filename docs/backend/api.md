# API Specification

**Source:** §5 (plan.txt)

---

## Base URL

```
https://platform.example.com/api/v1
```

---

## Authentication

### Sanctum (Admin API)
```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

Response:
```json
{
  "token": "abc123...",
  "user": { ... }
}
```

Subsequent requests:
```http
Authorization: Bearer abc123...
```

### Public API
No authentication required for public endpoints.

---

## Public Endpoints

### Posts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/posts` | List posts (published only) |
| GET | `/api/v1/posts/{slug}` | Get single post |
| GET | `/api/v1/posts/{slug}/related` | Get related posts |

**Query Parameters (GET /api/v1/posts):**
- `category` — filter by category slug
- `tag` — filter by tag slug
- `author` — filter by author ID
- `page` — page number (default: 1)
- `per_page` — items per page (default: 20, max: 100)
- `sort` — `published_at` (default), `title`
- `order` — `desc` (default), `asc`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Article Title",
      "slug": "article-title",
      "excerpt": "...",
      "featured_image_url": "/media/2026/09/photo-medium.webp",
      "published_at": "2026-09-30T10:00:00Z",
      "author_name": "John Doe",
      "category_name": "Sports"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200
  }
}
```

### Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/categories` | List categories |
| GET | `/api/v1/categories/{slug}` | Get single category |
| GET | `/api/v1/categories/{slug}/posts` | Get posts in category |

### Tags

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/tags` | List tags |
| GET | `/api/v1/tags/{slug}` | Get single tag |
| GET | `/api/v1/tags/{slug}/posts` | Get posts with tag |

### Authors

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/authors` | List authors |
| GET | `/api/v1/authors/{id}` | Get single author |
| GET | `/api/v1/authors/{id}/posts` | Get posts by author |

### Search

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/search` | Search posts |

**Query Parameters:**
- `q` — search query (required)
- `page` — page number
- `per_page` — items per page

**Response:**
```json
{
  "data": [...],
  "meta": { ... }
}
```

### Pages

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/pages/{slug}` | Get single page |

### Menus

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/menus/{name}` | Get menu by name |

### Media

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/media/{id}` | Get media file details |

---

## Admin Endpoints (Authenticated)

### Auth

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | Login |
| POST | `/api/v1/auth/logout` | Logout |
| GET | `/api/v1/auth/me` | Get current user |

### Posts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/posts` | List all posts (including drafts) |
| POST | `/api/v1/admin/posts` | Create post |
| GET | `/api/v1/admin/posts/{id}` | Get post |
| PUT | `/api/v1/admin/posts/{id}` | Update post |
| DELETE | `/api/v1/admin/posts/{id}` | Delete post |
| POST | `/api/v1/admin/posts/{id}/publish` | Publish post |
| POST | `/api/v1/admin/posts/{id}/archive` | Archive post |

### Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/categories` | List categories |
| POST | `/api/v1/admin/categories` | Create category |
| PUT | `/api/v1/admin/categories/{id}` | Update category |
| DELETE | `/api/v1/admin/categories/{id}` | Delete category |

### Tags

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/tags` | List tags |
| POST | `/api/v1/admin/tags` | Create tag |
| PUT | `/api/v1/admin/tags/{id}` | Update tag |
| DELETE | `/api/v1/admin/tags/{id}` | Delete tag |

### Authors

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/authors` | List authors |
| POST | `/api/v1/admin/authors` | Create author |
| PUT | `/api/v1/admin/authors/{id}` | Update author |
| DELETE | `/api/v1/admin/authors/{id}` | Delete author |

### Media

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/media` | List media |
| POST | `/api/v1/admin/media` | Upload media |
| PUT | `/api/v1/admin/media/{id}` | Update media metadata |
| DELETE | `/api/v1/admin/media/{id}` | Delete media |
| POST | `/api/v1/admin/media/{id}/variants` | Regenerate variants |

### Projects

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/projects` | List projects |
| POST | `/api/v1/admin/projects` | Create project |
| GET | `/api/v1/admin/projects/{id}` | Get project |
| PUT | `/api/v1/admin/projects/{id}` | Update project |
| DELETE | `/api/v1/admin/projects/{id}` | Delete project |
| POST | `/api/v1/admin/projects/{id}/generate` | Generate site |
| POST | `/api/v1/admin/projects/{id}/deploy` | Deploy site |

### Themes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/themes` | List themes |
| GET | `/api/v1/admin/themes/{id}` | Get theme details |
| POST | `/api/v1/admin/themes` | Register theme |
| PUT | `/api/v1/admin/themes/{id}` | Update theme |
| DELETE | `/api/v1/admin/themes/{id}` | Delete theme |

### Components

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/components` | List components |
| GET | `/api/v1/admin/components/{id}` | Get component details |
| POST | `/api/v1/admin/components` | Register component |
| PUT | `/api/v1/admin/components/{id}` | Update component |
| DELETE | `/api/v1/admin/components/{id}` | Delete component |

### Clients

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/clients` | List clients |
| POST | `/api/v1/admin/clients` | Create client |
| GET | `/api/v1/admin/clients/{id}` | Get client |
| PUT | `/api/v1/admin/clients/{id}` | Update client |
| DELETE | `/api/v1/admin/clients/{id}` | Delete client |

### Licenses

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/admin/licenses` | List licenses |
| POST | `/api/v1/admin/licenses` | Create license |
| GET | `/api/v1/admin/licenses/{id}` | Get license |
| PUT | `/api/v1/admin/licenses/{id}` | Update license |
| DELETE | `/api/v1/admin/licenses/{id}` | Delete license |

---

## Rate Limiting

| Endpoint Group | Limit |
|---------------|-------|
| Public API | 60 req/min per IP |
| Admin API | 120 req/min per user |
| Login | 5 attempts/min per IP |
| Search | 30 req/min per IP |

---

## Error Format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "title": ["The title field is required."]
    }
  }
}
```

---

## Related Documents

- [Backend Overview](./overview.md)
- [Entities](./entities.md)
- [Media System](./media-system.md)
- [Migrations](./migrations.md)
