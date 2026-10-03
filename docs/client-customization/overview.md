# Client Customization Model

**Source:** §19 (plan.txt)

---

## Overview

Client customization is separated into four levels. Clients can modify Levels 1-3 but never Level 4.

---

## 4-Level Model

```
LEVEL 1: CONTENT
├── articles
├── categories
├── authors
├── images
├── menus
├── pages
└── advertisements

LEVEL 2: CONFIGURATION
├── colors
├── fonts
├── logo
├── header style
├── footer style
├── sidebar
└── homepage layout

LEVEL 3: EXTENSIONS
├── custom CSS
├── custom JS
├── custom component
└── theme override

LEVEL 4: CORE (NEVER client-modified)
├── Laravel
├── platform engine
└── core React runtime
```

---

## Level 1: Content

Clients manage their own content through the admin interface.

| Content Type | Description |
|-------------|-------------|
| Articles | News posts with title, body, excerpt, featured image |
| Categories | Hierarchical content organization |
| Authors | Author profiles with bio, avatar, social links |
| Images | Media library with variants |
| Menus | Navigation structure |
| Pages | Static pages (About, Contact, etc.) |
| Advertisements | Ad placements and content |

**Platform role:** Provides the content management interface.
**Client role:** Creates and manages their own content.

---

## Level 2: Configuration

Clients customize the appearance through configuration, not code.

| Setting | Description |
|---------|-------------|
| Colors | Primary, secondary, background, text, muted, border |
| Fonts | Heading font, body font |
| Logo | Site logo image |
| Header style | Layout, sticky, transparent |
| Footer style | Layout, columns, copyright |
| Sidebar | Position (left, right, none), widgets |
| Homepage layout | Section order, component selection |

**Platform role:** Provides configuration UI and stores configuration.
**Client role:** Adjusts settings through the builder.

---

## Level 3: Extensions

Clients can extend the platform with custom code.

| Extension | Description |
|-----------|-------------|
| Custom CSS | Additional CSS rules |
| Custom JS | Additional JavaScript |
| Custom component | Client-authored React component |
| Theme override | Override specific theme components |

**Platform role:** Provides extension points and sandboxed execution.
**Client role:** Writes custom code within the extension points.

### Custom Component Registration

```javascript
// Client registers a custom component
platform.registerComponent('custom-hero', CustomHeroComponent, {
  schema: CustomHeroSchema,
  version: '1.0.0',
});
```

### Theme Override

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

---

## Level 4: Core (Platform-Only)

Clients NEVER directly modify Level 4.

| Component | Description |
|-----------|-------------|
| Laravel | Backend framework, API, admin |
| Platform engine | Core business logic |
| Core React runtime | Component system, renderer, theme engine |

**Platform role:** Controls all core code.
**Client role:** No access.

---

## Update Safety

| Level | Update Behavior |
|-------|----------------|
| 1. Content | Never overwritten by platform updates |
| 2. Configuration | Preserved during updates |
| 3. Extensions | Preserved during updates; may need compatibility updates |
| 4. Core | Updated by platform; clients never modify |

---

## Related Documents

- [Theme System](../frontend/theme-system.md)
- [Component System](../frontend/component-system.md)
- [Update System](../deployment/update-system.md)
- [Gap Analysis](../gap-analysis.md) — GAP-005 (override resolution)
