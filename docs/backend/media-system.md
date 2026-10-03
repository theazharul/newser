# Media System

**Source:** §8 (plan.txt)

---

## Overview

Media is a first-class entity. The system supports images, videos, and documents with automatic variant generation.

---

## Supported Media Types

| Type | Extensions | Variants |
|------|-----------|----------|
| Images | jpg, jpeg, png, gif, webp, avif | thumbnail, small, medium, large, webp, avif |
| Videos | mp4, webm, mov | thumbnail (poster), compressed |
| Documents | pdf, doc, docx | none |

---

## Image Variants

| Variant | Width | Height | Format | Use Case |
|---------|-------|--------|--------|----------|
| thumbnail | 150 | 150 | webp | Admin preview |
| small | 300 | 200 | webp | Mobile cards |
| medium | 600 | 400 | webp | News cards |
| large | 1200 | 800 | webp | Featured images |
| original | — | — | original | Full size |

---

## Media Upload Flow

```
Upload → Validate (MIME, size, dimensions) → Store original → Generate variants → Save metadata
```

### Validation Rules
- **MIME type:** check actual file content, not just extension
- **File size:** max 10MB (images), max 100MB (videos)
- **Image dimensions:** max 8000x8000
- **Filename:** sanitize, remove special characters
- **Upload permissions:** check user role

### Storage Strategy
- **MVP:** local filesystem (`storage/app/public/media`)
- **Phase 2:** S3-compatible storage (MinIO for self-hosted)
- **CDN:** Cloudflare or CloudFront in front of storage

---

## Media Processing

### Image Processing
- **Library:** `spatie/laravel-medialibrary` + `spatie/image`
- **Driver:** GD or ImageMagick
- **Processing:** synchronous on upload (no queue for MVP)
- **WebP/AVIF:** automatic conversion on upload

### Video Processing
- **Library:** FFmpeg (server-side binary)
- **Processing:** synchronous on upload (acceptable for MVP with small files)
- **Poster frame:** extract frame at 00:00:01
- **Compression:** optional, configurable

---

## Media API

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/media` | List media files |
| GET | `/api/v1/media/{id}` | Get media file details |
| POST | `/api/v1/media` | Upload media file |
| PUT | `/api/v1/media/{id}` | Update media metadata |
| DELETE | `/api/v1/media/{id}` | Delete media file |
| POST | `/api/v1/media/{id}/variants` | Regenerate variants |

### Upload Request
```http
POST /api/v1/media
Content-Type: multipart/form-data

file: <binary>
alt_text: "News photo"
caption: "Caption text"
credit: "Photographer name"
```

### Upload Response
```json
{
  "data": {
    "id": 1,
    "file_name": "photo.jpg",
    "file_path": "/media/2026/09/photo.jpg",
    "mime_type": "image/jpeg",
    "size": 204800,
    "width": 1920,
    "height": 1080,
    "alt_text": "News photo",
    "caption": "Caption text",
    "credit": "Photographer name",
    "variants": {
      "thumbnail": "/media/2026/09/photo-thumbnail.webp",
      "small": "/media/2026/09/photo-small.webp",
      "medium": "/media/2026/09/photo-medium.webp",
      "large": "/media/2026/09/photo-large.webp"
    }
  }
}
```

---

## Media in Components

Components receive media URLs, not file paths:

```typescript
interface NewsItem {
  id: number;
  title: string;
  featured_image_url: string;  // → /media/2026/09/photo-medium.webp
  featured_image_alt: string;
}
```

---

## Security

- **MIME validation:** check actual file content (`finfo_file`)
- **File size:** enforce max size
- **Image dimensions:** enforce max dimensions
- **Filename:** sanitize, remove special characters
- **Upload permissions:** check user role
- **XSS protection:** escape alt text, caption, credit
- **Storage:** outside web root, serve via controller

---

## Related Documents

- [Backend Overview](./overview.md)
- [Entities](./entities.md)
- [API](./api.md)
- [Gap Analysis](../gap-analysis.md) — GAP-003 (media transcoding)
