# Required Variables for Modern Layout Partials

## Global Variables Available in Header Context

### Required Variables:
- `$mode` - Theme mode ('dark' or 'light') from cookie
- `$basePath` - TenantContext base path (with trailing slash)
- `$user_id` - Current user ID from session (null if not logged in)
- `$pageTitle` / `$hTitle` - Page title for SEO
- `$hSubtitle` - Page subtitle/description
- `$isHome` - Boolean indicating if current page is homepage

### Session Variables:
- `$_SESSION['user_id']` - User ID
- `$_SESSION['user_name']` - User display name
- `$_SESSION['user_avatar']` - Avatar URL
- `$_SESSION['user_role']` - User role (admin, member, etc.)

### Available Functions:
- `layout()` - Returns current layout slug ('modern', 'civicone', etc.)
- `is_modern()` - Check if modern layout is active
- `TenantContext::getBasePath()` - Get tenant base path
- `TenantContext::getId()` - Get tenant ID
- `Csrf::generate()` - Generate CSRF token

### Database Classes:
- `\Nexus\Core\Database` - Database connection
- `\Nexus\Models\User` - User model
- `\Nexus\Models\Notification` - Notifications
- `\Nexus\Models\MessageThread` - Messages
- `\Nexus\Core\MenuManager` - Menu management
- `\Nexus\Services\LayoutHelper` - Layout utilities

## Partial Dependencies

### utility-bar.php
- Requires: `$mode`, `$basePath`, `$user_id`
- Uses: Session variables, TenantContext, User model

### desktop-navigation.php
- Requires: `$basePath`, `$user_id`
- Uses: MenuManager, TenantContext

### native-drawer.php
- Requires: `$basePath`, `$user_id`, `$mode`
- Uses: Session variables, User model

### notifications-drawer.php
- Requires: `$user_id`, `$basePath`
- Uses: Notification model

## CSS Files Extracted
- `css/premium-search.css` - Search component styles
- `css/premium-dropdowns.css` - Dropdown menu styles

## JS Files Extracted
- `/assets/js/modern-header-behavior.js` - All header JavaScript functionality
