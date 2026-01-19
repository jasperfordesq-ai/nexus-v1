# Admin Gold Standard Components

This directory contains the unified admin header and footer components for the NEXUS Admin interface.

## Files

| File | Purpose |
|------|---------|
| `admin-header.php` | Smart Tab Bar navigation header - include at top of admin pages |
| `admin-footer.php` | Closes wrapper divs, adds toast notifications - include at bottom |

## Quick Start

To add the Gold Standard design to any admin page:

```php
<?php
use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Optional: Customize the header
$adminPageTitle = 'Page Title';
$adminPageSubtitle = 'Subtitle';
$adminPageIcon = 'fa-users'; // Font Awesome icon

// 1. Include main site header first
require dirname(__DIR__, 2) . '/layouts/header.php';

// 2. Include admin header (Smart Tab Bar)
require __DIR__ . '/partials/admin-header.php';
?>

<!-- Your page content here -->
<div class="admin-page-header">
    <h1 class="admin-page-title">Your Page</h1>
</div>

<!-- Your content... -->

<?php
// 3. Include admin footer (closes wrapper)
require __DIR__ . '/partials/admin-footer.php';

// 4. Include main site footer
require dirname(__DIR__, 2) . '/layouts/footer.php';
?>
```

## What's Included

### Smart Tab Navigation

The header includes a complete navigation system with these modules:

- **Dashboard** - Main admin overview
- **Users** - User management, approvals, badges
- **Content** - Blog, pages, categories, attributes
- **Listings** - All listings, pending approval
- **Community** - Volunteering, organizations, group locations
- **Engagement** - Gamification, campaigns, analytics
- **Newsletters** - All newsletters, subscribers, segments, templates
- **SEO** - Overview, audit, bulk edit, redirects
- **AI & Smart** - AI settings, smart matching, feed algorithm
- **Timebanking** - Dashboard, alerts, org wallets
- **Enterprise** - Overview, GDPR, monitoring, configuration
- **System** - Settings, cron jobs, activity log, native app

### JavaScript Utilities

The footer includes these global utilities:

```javascript
// Toast Notifications
AdminToast.success('Title', 'Message');
AdminToast.error('Title', 'Message');
AdminToast.warning('Title', 'Message');
AdminToast.info('Title', 'Message');

// Modal Control
AdminModal.show('modalId');
AdminModal.hide('modalId');
```

### CSS Classes Available

Use these classes from the Gold Standard CSS:

```html
<!-- Cards -->
<div class="admin-glass-card">
    <div class="admin-card-header">...</div>
    <div class="admin-card-body">...</div>
</div>

<!-- Buttons -->
<button class="admin-btn admin-btn-primary">Primary</button>
<button class="admin-btn admin-btn-secondary">Secondary</button>
<button class="admin-btn admin-btn-warning">Warning</button>

<!-- Badges -->
<span class="admin-badge admin-badge-success">Active</span>
<span class="admin-badge admin-badge-warning">Pending</span>
<span class="admin-badge admin-badge-danger">Error</span>

<!-- Alerts -->
<div class="admin-alert admin-alert-warning">
    <div class="admin-alert-icon"><i class="fa-solid fa-warning"></i></div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Title</div>
        <div class="admin-alert-text">Message</div>
    </div>
</div>
```

## Design System

The Gold Standard uses:

- **Colors**: Dark glassmorphism with indigo/purple accent gradients
- **Typography**: Inter for UI, JetBrains Mono for code
- **Icons**: Font Awesome 6
- **Effects**: Backdrop blur, subtle glow effects, smooth transitions

## File Structure

```
views/modern/admin/
    partials/
        admin-header.php    <- Navigation header
        admin-footer.php    <- Footer utilities
        README.md           <- This file
    dashboard.php           <- Example implementation
```

## Mobile Support

The navigation automatically collapses on mobile devices (<1024px) with a hamburger menu toggle.
