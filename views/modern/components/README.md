# Modern Theme Component Library

A comprehensive library of reusable PHP components for the Project NEXUS modern theme.

## Overview

- **79 components** across **8 categories**
- **CSS file**: `/httpdocs/assets/css/modern/components-library.css`
- **Preview page**: Include `_preview.php` to see all components
- **Design tokens**: Uses variables from `/httpdocs/assets/css/design-tokens.css`

## Categories

### 1. Layout Components (`/layout/`)

| Component | File | Description |
|-----------|------|-------------|
| Container | `container.php` | Responsive content wrapper |
| Grid | `grid.php` | CSS Grid layout system |
| Hero | `hero.php` | Hero section with title, subtitle, actions |
| Section | `section.php` | Content section with header and actions |
| Sidebar Layout | `sidebar-layout.php` | Two-column layout with sidebar |

### 2. Navigation Components (`/navigation/`)

| Component | File | Description |
|-----------|------|-------------|
| Breadcrumb | `breadcrumb.php` | Breadcrumb navigation trail |
| Dropdown | `dropdown.php` | Dropdown menu |
| Filter Bar | `filter-bar.php` | Filter controls with search |
| Pagination | `pagination.php` | Page navigation |
| Pills | `pills.php` | Pill-style navigation |
| Tabs | `tabs.php` | Tab navigation |

### 3. Card Components (`/cards/`)

| Component | File | Description |
|-----------|------|-------------|
| Card | `card.php` | Base card component |
| Achievement Card | `achievement-card.php` | Badge/achievement display |
| Event Card | `event-card.php` | Event with date badge |
| Group Card | `group-card.php` | Community group card |
| Listing Card | `listing-card.php` | Service listing display |
| Member Card | `member-card.php` | User profile card |
| Resource Card | `resource-card.php` | Resource/file card |
| Stat Card | `stat-card.php` | Statistics display |
| Volunteer Card | `volunteer-card.php` | Volunteer opportunity |

### 4. Form Components (`/forms/`)

| Component | File | Description |
|-----------|------|-------------|
| Checkbox | `checkbox.php` | Checkbox with label |
| Date Picker | `date-picker.php` | Date input |
| File Upload | `file-upload.php` | Drag-and-drop file upload |
| Form Group | `form-group.php` | Label + input wrapper |
| Input | `input.php` | Text input with icon support |
| Radio | `radio.php` | Radio button group |
| Range Slider | `range-slider.php` | Slider input |
| Rich Text Editor | `rich-text-editor.php` | WYSIWYG editor |
| Search Input | `search-input.php` | Search with clear button |
| Select | `select.php` | Dropdown select |
| Textarea | `textarea.php` | Multi-line text input |
| Time Picker | `time-picker.php` | Time input |
| Toggle Switch | `toggle-switch.php` | On/off toggle |

### 5. Button Components (`/buttons/`)

| Component | File | Description |
|-----------|------|-------------|
| Button | `button.php` | Primary button component |
| Button Group | `button-group.php` | Grouped buttons |
| FAB | `fab.php` | Floating action button |
| Icon Button | `icon-button.php` | Icon-only button |

### 6. Feedback Components (`/feedback/`)

| Component | File | Description |
|-----------|------|-------------|
| Alert | `alert.php` | Alert messages (info, success, warning, danger) |
| Empty State | `empty-state.php` | No content placeholder |
| Loading Spinner | `loading-spinner.php` | Loading indicators |
| Modal | `modal.php` | Modal dialog |
| Skeleton | `skeleton.php` | Loading skeleton |
| Toast | `toast.php` | Toast notifications |

### 7. Media Components (`/media/`)

| Component | File | Description |
|-----------|------|-------------|
| Avatar | `avatar.php` | User avatar with initials fallback |
| Avatar Stack | `avatar-stack.php` | Overlapping avatar group |
| Badge | `badge.php` | Status/label badge |
| Code Block | `code-block.php` | Syntax-highlighted code |
| Gallery | `gallery.php` | Image gallery with lightbox |
| Icon | `icon.php` | Font Awesome icon wrapper |
| Image | `image.php` | Responsive image |
| Video Embed | `video-embed.php` | YouTube/Vimeo embed |

### 8. Data Components (`/data/`)

| Component | File | Description |
|-----------|------|-------------|
| Leaderboard | `leaderboard.php` | Ranking list |
| List | `list.php` | Styled list component |
| Progress Bar | `progress-bar.php` | Progress indicator |
| Stat | `stat.php` | Single statistic display |
| Table | `table.php` | Data table |
| Timeline Item | `timeline-item.php` | Timeline entry |

### 9. Interactive Components (`/interactive/`)

| Component | File | Description |
|-----------|------|-------------|
| Accordion | `accordion.php` | Collapsible content |
| Copy Button | `copy-button.php` | Copy to clipboard |
| Draggable List | `draggable-list.php` | Reorderable list |
| Poll Voting | `poll-voting.php` | Poll with results |
| Share Button | `share-button.php` | Social share dropdown |
| Star Rating | `star-rating.php` | Interactive rating |
| Tooltip | `tooltip.php` | Hover tooltip |

### 10. Social Components (`/social/`)

| Component | File | Description |
|-----------|------|-------------|
| Comment Section | `comment-section.php` | Comments with replies |
| Notification Item | `notification-item.php` | Notification display |
| Profile Header | `profile-header.php` | User profile header |

### 11. Shared Components (`/shared/`)

| Component | File | Description |
|-----------|------|-------------|
| Post Card | `post-card.php` | Social feed post |

### 12. Nexus Dashboard Components (root)

| Component | File | Description |
|-----------|------|-------------|
| Nexus Leaderboard | `nexus-leaderboard.php` | Community leaderboard |
| Nexus Score Charts | `nexus-score-charts.php` | Radar/bar charts |
| Nexus Score Dashboard | `nexus-score-dashboard.php` | Main score display |
| Nexus Score Widget | `nexus-score-widget.php` | Compact score widget |

## Usage

### Basic Example

```php
<?php
// Set component variables
$label = 'Submit';
$variant = 'primary';
$icon = 'paper-plane';

// Include the component
include __DIR__ . '/components/buttons/button.php';
?>
```

### Card Example

```php
<?php
$listing = [
    'id' => 1,
    'title' => 'Guitar Lessons',
    'description' => 'Learn to play guitar...',
    'type' => 'offer',
    'category_name' => 'Education',
    'price' => 2,
];
$user = ['id' => 1, 'name' => 'John Doe', 'avatar' => ''];

include __DIR__ . '/components/cards/listing-card.php';
?>
```

### Form Example

```php
<?php
$label = 'Email Address';
$name = 'email';
$type = 'email';
$placeholder = 'you@example.com';
$icon = 'envelope';
$required = true;
$error = '';
$help = 'We will never share your email.';

ob_start();
include __DIR__ . '/components/forms/input.php';
$content = ob_get_clean();

include __DIR__ . '/components/forms/form-group.php';
?>
```

## CSS Classes

All components use BEM-style CSS classes:

```css
.component-{name}           /* Block */
.component-{name}__{element}  /* Element */
.component-{name}--{modifier} /* Modifier */
```

### Examples

```css
.component-btn              /* Button block */
.component-btn__icon        /* Button icon element */
.component-btn--primary     /* Primary variant modifier */
.component-btn--lg          /* Large size modifier */
```

## Design Tokens

Components use CSS custom properties from `design-tokens.css`:

```css
/* Colors */
var(--color-primary-500)
var(--color-success)
var(--color-danger)
var(--color-text)
var(--color-text-muted)

/* Spacing */
var(--space-1) /* 4px */
var(--space-2) /* 8px */
var(--space-3) /* 12px */
var(--space-4) /* 16px */

/* Typography */
var(--font-size-sm)
var(--font-size-base)
var(--font-size-lg)
var(--font-weight-medium)
var(--font-weight-semibold)

/* Borders */
var(--radius-md)
var(--radius-lg)
var(--radius-xl)
var(--radius-full)

/* Shadows */
var(--shadow-sm)
var(--shadow-md)
var(--shadow-lg)
```

## Accessibility

All components include:

- Semantic HTML elements
- ARIA labels where needed
- Keyboard navigation support
- Focus states
- Screen reader support

## File Structure

```
views/modern/components/
├── _init.php              # Component helpers
├── _preview.php           # Visual preview page
├── README.md              # This documentation
├── buttons/
│   ├── button.php
│   ├── button-group.php
│   ├── fab.php
│   └── icon-button.php
├── cards/
│   ├── card.php
│   ├── achievement-card.php
│   ├── event-card.php
│   └── ...
├── data/
├── feedback/
├── forms/
├── interactive/
├── layout/
├── media/
├── navigation/
├── shared/
└── social/
```

## Contributing

When adding new components:

1. Create the PHP file in the appropriate category folder
2. Add CSS classes to `/httpdocs/assets/css/modern/components-library.css`
3. Use design tokens instead of hardcoded values
4. Follow BEM naming convention
5. Include ARIA attributes for accessibility
6. Add example to `_preview.php`
7. Update this README

## Related Files

- **CSS**: `/httpdocs/assets/css/modern/components-library.css`
- **Design Tokens**: `/httpdocs/assets/css/design-tokens.css`
- **Preview CSS**: `/httpdocs/assets/css/modern/preview.css`
- **PurgeCSS Config**: `/purgecss.config.js`
