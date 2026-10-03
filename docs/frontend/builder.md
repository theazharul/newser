# Visual Builder

**Source:** §20-21 (plan.txt)

---

## Overview

The visual builder is a React application that allows users to design news websites visually with drag-and-drop.

---

## Builder UI Layout

```
+---------------------------------------------------------+
| News Builder                       [Preview] [Generate] |
+----------------+--------------------------+-------------+
| Components     |                          | Settings    |
|                |      LIVE WEBSITE        |             |
| Hero           |                          | Title       |
| News Grid      |          HERO            | Category    |
| News List      |                          | Columns     |
| Breaking News  |      NEWS GRID           | Limit       |
| Video Grid     |                          |             |
| Advertisement  |      NEWS LIST           |             |
+----------------+--------------------------+-------------+
```

---

## Builder Features

| Feature | Description |
|---------|-------------|
| Add section | Click component in palette to add to page |
| Remove section | Click delete button on section |
| Duplicate section | Click duplicate button on section |
| Reorder section | Drag and drop sections |
| Configure section | Edit settings in right panel |
| Theme selector | Switch between available themes |
| Color customization | Modify theme tokens |
| Typography | Change fonts |
| Demo content toggle | Show/hide demo data |
| Live preview | Real-time preview of changes |
| Generate | Create deployment package |

---

## Homepage Configuration

```json
{
  "page": "homepage",
  "sections": [
    {
      "id": "section-1",
      "component": "breaking-news",
      "props": {}
    },
    {
      "id": "section-2",
      "component": "hero",
      "props": {
        "category": "national"
      }
    },
    {
      "id": "section-3",
      "component": "news-grid",
      "props": {
        "title": "Sports",
        "category": "sports",
        "limit": 8,
        "columns": 4
      }
    },
    {
      "id": "section-4",
      "component": "advertisement",
      "props": {
        "position": "homepage-inline"
      }
    },
    {
      "id": "section-5",
      "component": "video-grid",
      "props": {
        "title": "Videos",
        "limit": 6
      }
    }
  ]
}
```

---

## Builder State Management

```typescript
// packages/news-builder/src/store.ts
import { create } from 'zustand';

interface BuilderState {
  project: Project;
  theme: Theme;
  sections: Section[];
  selectedSection: string | null;
  previewMode: boolean;

  // Actions
  addSection: (componentId: string) => void;
  removeSection: (sectionId: string) => void;
  duplicateSection: (sectionId: string) => void;
  reorderSections: (fromIndex: number, toIndex: number) => void;
  updateSection: (sectionId: string, props: Record<string, unknown>) => void;
  selectSection: (sectionId: string | null) => void;
  setTheme: (themeId: string) => void;
  updateThemeConfig: (config: Partial<ThemeConfig>) => void;
  togglePreview: () => void;
  generate: () => Promise<void>;
}

export const useBuilderStore = create<BuilderState>((set, get) => ({
  project: null,
  theme: null,
  sections: [],
  selectedSection: null,
  previewMode: false,

  addSection: (componentId) => {
    const component = registry[componentId];
    const newSection = {
      id: `section-${Date.now()}`,
      component: componentId,
      version: component.version,
      props: component.defaultProps,
    };
    set((state) => ({ sections: [...state.sections, newSection] }));
  },

  removeSection: (sectionId) => {
    set((state) => ({
      sections: state.sections.filter((s) => s.id !== sectionId),
    }));
  },

  duplicateSection: (sectionId) => {
    const section = get().sections.find((s) => s.id === sectionId);
    if (section) {
      const duplicate = {
        ...section,
        id: `section-${Date.now()}`,
      };
      set((state) => ({ sections: [...state.sections, duplicate] }));
    }
  },

  reorderSections: (fromIndex, toIndex) => {
    set((state) => {
      const sections = [...state.sections];
      const [moved] = sections.splice(fromIndex, 1);
      sections.splice(toIndex, 0, moved);
      return { sections };
    });
  },

  updateSection: (sectionId, props) => {
    set((state) => ({
      sections: state.sections.map((s) =>
        s.id === sectionId ? { ...s, props: { ...s.props, ...props } } : s
      ),
    }));
  },

  selectSection: (sectionId) => set({ selectedSection: sectionId }),

  setTheme: (themeId) => {
    const theme = themeRegistry[themeId];
    set({ theme });
  },

  updateThemeConfig: (config) => {
    set((state) => ({
      theme: { ...state.theme, config: { ...state.theme.config, ...config } },
    }));
  },

  togglePreview: () => set((state) => ({ previewMode: !state.previewMode })),

  generate: async () => {
    const { project, theme, sections } = get();
    const response = await fetch(`/api/v1/admin/projects/${project.id}/generate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ theme, sections }),
    });
    return response.json();
  },
}));
```

---

## Preview Mode

The preview renderer uses the same component system as production:

```
Builder → Project Configuration → React Preview Renderer → Live Website
```

**Critical rule:** The exact same component system powers both Builder Preview and Generated Client Website. This prevents "Preview looks different from production."

---

## Demo Content

Every theme/project preview has demo data:
- Articles
- Categories
- Authors
- Images
- Videos

Demo content is stored in `packages/news-renderer/demo-data/` and can be:
- Removed
- Replaced
- Imported as starter content

---

## Related Documents

- [Frontend Overview](./overview.md)
- [Component System](./component-system.md)
- [Theme System](./theme-system.md)
- [Renderer](../generator/process.md)
- [Gap Analysis](../gap-analysis.md) — GAP-002 (phase order)
