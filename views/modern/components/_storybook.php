<?php

/**
 * Storybook-Style Component Documentation
 *
 * Interactive component library with:
 * - Live component previews
 * - Props/API tables
 * - Code snippets
 * - Multiple variants and states
 * - Search and navigation
 *
 * Access via: /components or include directly
 */

// Include component helpers
require_once __DIR__ . '/_init.php';

// Component registry with documentation
$componentRegistry = [
    'layout' => [
        'label' => 'Layout',
        'icon' => 'layer-group',
        'components' => [
            'hero' => [
                'name' => 'Hero',
                'file' => 'layout/hero.php',
                'description' => 'Full-width hero section with title, subtitle, and call-to-action buttons.',
                'props' => [
                    ['name' => 'title', 'type' => 'string', 'default' => "''", 'description' => 'Main heading text'],
                    ['name' => 'subtitle', 'type' => 'string', 'default' => "''", 'description' => 'Supporting text below title'],
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'FontAwesome icon name'],
                    ['name' => 'badge', 'type' => 'array', 'default' => '[]', 'description' => "Badge config: ['icon', 'text']"],
                    ['name' => 'buttons', 'type' => 'array', 'default' => '[]', 'description' => "Array of button configs"],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'default'", 'description' => "'default', 'centered', 'compact'"],
                    ['name' => 'class', 'type' => 'string', 'default' => "''", 'description' => 'Additional CSS classes'],
                ],
            ],
            'section' => [
                'name' => 'Section',
                'file' => 'layout/section.php',
                'description' => 'Content section with optional title, icon, and action buttons.',
                'props' => [
                    ['name' => 'title', 'type' => 'string', 'default' => "''", 'description' => 'Section heading'],
                    ['name' => 'subtitle', 'type' => 'string', 'default' => "''", 'description' => 'Description text'],
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'FontAwesome icon name'],
                    ['name' => 'actions', 'type' => 'array', 'default' => '[]', 'description' => 'Action button configs'],
                    ['name' => 'content', 'type' => 'string', 'default' => "''", 'description' => 'Section content (HTML)'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'default'", 'description' => "'default', 'card', 'flat'"],
                ],
            ],
            'container' => [
                'name' => 'Container',
                'file' => 'layout/container.php',
                'description' => 'Responsive container with configurable max-width.',
                'props' => [
                    ['name' => 'size', 'type' => 'string', 'default' => "'lg'", 'description' => "'sm', 'md', 'lg', 'xl', 'full'"],
                    ['name' => 'class', 'type' => 'string', 'default' => "''", 'description' => 'Additional CSS classes'],
                ],
            ],
            'grid' => [
                'name' => 'Grid',
                'file' => 'layout/grid.php',
                'description' => 'Responsive grid layout with configurable columns.',
                'props' => [
                    ['name' => 'cols', 'type' => 'int', 'default' => '3', 'description' => 'Number of columns (1-6)'],
                    ['name' => 'gap', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg'"],
                    ['name' => 'items', 'type' => 'array', 'default' => '[]', 'description' => 'Grid item contents'],
                ],
            ],
            'sidebar-layout' => [
                'name' => 'Sidebar Layout',
                'file' => 'layout/sidebar-layout.php',
                'description' => 'Two-column layout with sidebar and main content area.',
                'props' => [
                    ['name' => 'sidebarContent', 'type' => 'string', 'default' => "''", 'description' => 'Sidebar HTML content'],
                    ['name' => 'mainContent', 'type' => 'string', 'default' => "''", 'description' => 'Main area HTML content'],
                    ['name' => 'sidebarPosition', 'type' => 'string', 'default' => "'left'", 'description' => "'left' or 'right'"],
                    ['name' => 'sidebarWidth', 'type' => 'string', 'default' => "'300px'", 'description' => 'Sidebar width'],
                ],
            ],
        ],
    ],
    'navigation' => [
        'label' => 'Navigation',
        'icon' => 'compass',
        'components' => [
            'breadcrumb' => [
                'name' => 'Breadcrumb',
                'file' => 'navigation/breadcrumb.php',
                'description' => 'Navigation breadcrumb trail showing page hierarchy.',
                'props' => [
                    ['name' => 'items', 'type' => 'array', 'default' => '[]', 'description' => "Array of ['label', 'href'] items"],
                    ['name' => 'separator', 'type' => 'string', 'default' => "'/'", 'description' => 'Separator character'],
                    ['name' => 'class', 'type' => 'string', 'default' => "''", 'description' => 'Additional CSS classes'],
                ],
            ],
            'tabs' => [
                'name' => 'Tabs',
                'file' => 'navigation/tabs.php',
                'description' => 'Tab navigation with optional icons and counts.',
                'props' => [
                    ['name' => 'tabs', 'type' => 'array', 'default' => '[]', 'description' => "Array of ['id', 'label', 'icon', 'count']"],
                    ['name' => 'activeTab', 'type' => 'string', 'default' => "''", 'description' => 'Active tab ID'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'default'", 'description' => "'default', 'pills', 'underline'"],
                ],
            ],
            'pills' => [
                'name' => 'Pills',
                'file' => 'navigation/pills.php',
                'description' => 'Horizontal pill-style navigation for filtering.',
                'props' => [
                    ['name' => 'items', 'type' => 'array', 'default' => '[]', 'description' => "Array of ['id', 'label', 'icon']"],
                    ['name' => 'active', 'type' => 'string', 'default' => "''", 'description' => 'Active item ID'],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg'"],
                ],
            ],
            'pagination' => [
                'name' => 'Pagination',
                'file' => 'navigation/pagination.php',
                'description' => 'Page navigation with numbered links.',
                'props' => [
                    ['name' => 'currentPage', 'type' => 'int', 'default' => '1', 'description' => 'Current page number'],
                    ['name' => 'totalPages', 'type' => 'int', 'default' => '1', 'description' => 'Total number of pages'],
                    ['name' => 'baseUrl', 'type' => 'string', 'default' => "'?page='", 'description' => 'URL base for page links'],
                    ['name' => 'maxVisible', 'type' => 'int', 'default' => '5', 'description' => 'Max visible page numbers'],
                ],
            ],
            'filter-bar' => [
                'name' => 'Filter Bar',
                'file' => 'navigation/filter-bar.php',
                'description' => 'Filter navigation with optional search input.',
                'props' => [
                    ['name' => 'filters', 'type' => 'array', 'default' => '[]', 'description' => "Array of ['id', 'label', 'icon', 'count']"],
                    ['name' => 'active', 'type' => 'string', 'default' => "''", 'description' => 'Active filter ID'],
                    ['name' => 'showSearch', 'type' => 'bool', 'default' => 'false', 'description' => 'Show search input'],
                ],
            ],
            'dropdown' => [
                'name' => 'Dropdown',
                'file' => 'navigation/dropdown.php',
                'description' => 'Dropdown menu with trigger button.',
                'props' => [
                    ['name' => 'trigger', 'type' => 'string', 'default' => "''", 'description' => 'Trigger button HTML'],
                    ['name' => 'items', 'type' => 'array', 'default' => '[]', 'description' => 'Menu item configs'],
                    ['name' => 'align', 'type' => 'string', 'default' => "'left'", 'description' => "'left' or 'right'"],
                ],
            ],
        ],
    ],
    'cards' => [
        'label' => 'Cards',
        'icon' => 'square',
        'components' => [
            'card' => [
                'name' => 'Card (Base)',
                'file' => 'cards/card.php',
                'description' => 'Generic card component that serves as base for all card types.',
                'props' => [
                    ['name' => 'header', 'type' => 'string', 'default' => "''", 'description' => 'Card header HTML'],
                    ['name' => 'body', 'type' => 'string', 'default' => "''", 'description' => 'Card body HTML'],
                    ['name' => 'footer', 'type' => 'string', 'default' => "''", 'description' => 'Card footer HTML'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'glass'", 'description' => "'default', 'glass', 'elevated'"],
                    ['name' => 'href', 'type' => 'string', 'default' => "''", 'description' => 'Optional link URL'],
                ],
            ],
            'listing-card' => [
                'name' => 'Listing Card',
                'file' => 'cards/listing-card.php',
                'description' => 'Card for displaying service listings with image, price, and user info.',
                'props' => [
                    ['name' => 'listing', 'type' => 'array', 'default' => '[]', 'description' => 'Listing data object'],
                    ['name' => 'compact', 'type' => 'bool', 'default' => 'false', 'description' => 'Use compact layout'],
                    ['name' => 'showUser', 'type' => 'bool', 'default' => 'true', 'description' => 'Show user info'],
                ],
            ],
            'member-card' => [
                'name' => 'Member Card',
                'file' => 'cards/member-card.php',
                'description' => 'User profile card with avatar, bio, and connection actions.',
                'props' => [
                    ['name' => 'user', 'type' => 'array', 'default' => '[]', 'description' => 'User data object'],
                    ['name' => 'showBio', 'type' => 'bool', 'default' => 'true', 'description' => 'Show user bio'],
                    ['name' => 'showSkills', 'type' => 'bool', 'default' => 'true', 'description' => 'Show skills tags'],
                ],
            ],
            'event-card' => [
                'name' => 'Event Card',
                'file' => 'cards/event-card.php',
                'description' => 'Card for displaying events with date, location, and attendees.',
                'props' => [
                    ['name' => 'event', 'type' => 'array', 'default' => '[]', 'description' => 'Event data object'],
                    ['name' => 'compact', 'type' => 'bool', 'default' => 'false', 'description' => 'Use compact layout'],
                    ['name' => 'showAttendees', 'type' => 'bool', 'default' => 'true', 'description' => 'Show attendee avatars'],
                ],
            ],
            'stat-card' => [
                'name' => 'Stat Card',
                'file' => 'cards/stat-card.php',
                'description' => 'Card displaying a single metric with optional trend indicator.',
                'props' => [
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Stat label'],
                    ['name' => 'value', 'type' => 'string', 'default' => "'0'", 'description' => 'Stat value'],
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'FontAwesome icon'],
                    ['name' => 'trend', 'type' => 'string', 'default' => 'null', 'description' => "'up', 'down', or null"],
                    ['name' => 'trendValue', 'type' => 'string', 'default' => "''", 'description' => 'Trend percentage'],
                ],
            ],
            'achievement-card' => [
                'name' => 'Achievement Card',
                'file' => 'cards/achievement-card.php',
                'description' => 'Card for displaying badges and achievements.',
                'props' => [
                    ['name' => 'badge', 'type' => 'array', 'default' => '[]', 'description' => 'Badge data object'],
                    ['name' => 'showProgress', 'type' => 'bool', 'default' => 'false', 'description' => 'Show progress bar'],
                    ['name' => 'locked', 'type' => 'bool', 'default' => 'false', 'description' => 'Show as locked'],
                ],
            ],
            'group-card' => [
                'name' => 'Group Card',
                'file' => 'cards/group-card.php',
                'description' => 'Card for displaying community groups.',
                'props' => [
                    ['name' => 'group', 'type' => 'array', 'default' => '[]', 'description' => 'Group data object'],
                    ['name' => 'showMembers', 'type' => 'bool', 'default' => 'true', 'description' => 'Show member count'],
                ],
            ],
            'resource-card' => [
                'name' => 'Resource Card',
                'file' => 'cards/resource-card.php',
                'description' => 'Card for displaying downloadable resources.',
                'props' => [
                    ['name' => 'resource', 'type' => 'array', 'default' => '[]', 'description' => 'Resource data object'],
                    ['name' => 'showDownload', 'type' => 'bool', 'default' => 'true', 'description' => 'Show download button'],
                ],
            ],
            'volunteer-card' => [
                'name' => 'Volunteer Card',
                'file' => 'cards/volunteer-card.php',
                'description' => 'Card for displaying volunteer opportunities.',
                'props' => [
                    ['name' => 'opportunity', 'type' => 'array', 'default' => '[]', 'description' => 'Opportunity data object'],
                    ['name' => 'showOrg', 'type' => 'bool', 'default' => 'true', 'description' => 'Show organization'],
                ],
            ],
            'post-card' => [
                'name' => 'Post Card',
                'file' => 'cards/post-card.php',
                'description' => 'Social feed post card with reactions and comments.',
                'props' => [
                    ['name' => 'post', 'type' => 'array', 'default' => '[]', 'description' => 'Post data object'],
                    ['name' => 'showActions', 'type' => 'bool', 'default' => 'true', 'description' => 'Show action buttons'],
                ],
            ],
        ],
    ],
    'forms' => [
        'label' => 'Forms',
        'icon' => 'pen-to-square',
        'components' => [
            'input' => [
                'name' => 'Input',
                'file' => 'forms/input.php',
                'description' => 'Text input field with optional icon and validation states.',
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Input name attribute'],
                    ['name' => 'type', 'type' => 'string', 'default' => "'text'", 'description' => "'text', 'email', 'password', etc."],
                    ['name' => 'value', 'type' => 'string', 'default' => "''", 'description' => 'Input value'],
                    ['name' => 'placeholder', 'type' => 'string', 'default' => "''", 'description' => 'Placeholder text'],
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'FontAwesome icon'],
                    ['name' => 'disabled', 'type' => 'bool', 'default' => 'false', 'description' => 'Disable input'],
                    ['name' => 'required', 'type' => 'bool', 'default' => 'false', 'description' => 'Mark as required'],
                ],
            ],
            'textarea' => [
                'name' => 'Textarea',
                'file' => 'forms/textarea.php',
                'description' => 'Multi-line text input with auto-resize option.',
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Textarea name'],
                    ['name' => 'value', 'type' => 'string', 'default' => "''", 'description' => 'Textarea value'],
                    ['name' => 'rows', 'type' => 'int', 'default' => '3', 'description' => 'Number of rows'],
                    ['name' => 'autoResize', 'type' => 'bool', 'default' => 'false', 'description' => 'Auto-resize height'],
                ],
            ],
            'select' => [
                'name' => 'Select',
                'file' => 'forms/select.php',
                'description' => 'Dropdown select input with options.',
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Select name'],
                    ['name' => 'options', 'type' => 'array', 'default' => '[]', 'description' => 'Options as key => label'],
                    ['name' => 'selected', 'type' => 'string', 'default' => "''", 'description' => 'Selected value'],
                    ['name' => 'placeholder', 'type' => 'string', 'default' => "''", 'description' => 'Placeholder option'],
                ],
            ],
            'checkbox' => [
                'name' => 'Checkbox',
                'file' => 'forms/checkbox.php',
                'description' => 'Checkbox input with label and description.',
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Checkbox name'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Label text'],
                    ['name' => 'description', 'type' => 'string', 'default' => "''", 'description' => 'Help text'],
                    ['name' => 'checked', 'type' => 'bool', 'default' => 'false', 'description' => 'Checked state'],
                ],
            ],
            'radio' => [
                'name' => 'Radio',
                'file' => 'forms/radio.php',
                'description' => 'Radio button group for single selection.',
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Radio group name'],
                    ['name' => 'options', 'type' => 'array', 'default' => '[]', 'description' => 'Options array'],
                    ['name' => 'selected', 'type' => 'string', 'default' => "''", 'description' => 'Selected value'],
                ],
            ],
            'toggle-switch' => [
                'name' => 'Toggle Switch',
                'file' => 'forms/toggle-switch.php',
                'description' => 'On/off toggle switch control.',
                'usedOn' => ['settings', 'profile', 'admin configs', 'feature toggles'],
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Toggle name'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Label text'],
                    ['name' => 'checked', 'type' => 'bool', 'default' => 'false', 'description' => 'Checked state'],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg'"],
                ],
            ],
            'form-group' => [
                'name' => 'Form Group',
                'file' => 'forms/form-group.php',
                'description' => 'Wrapper for form fields with label, error, and help text.',
                'props' => [
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Field label'],
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Field name (for ID)'],
                    ['name' => 'error', 'type' => 'string', 'default' => "''", 'description' => 'Error message'],
                    ['name' => 'help', 'type' => 'string', 'default' => "''", 'description' => 'Help text'],
                    ['name' => 'required', 'type' => 'bool', 'default' => 'false', 'description' => 'Show required indicator'],
                    ['name' => 'content', 'type' => 'string', 'default' => "''", 'description' => 'Field content (HTML)'],
                ],
            ],
            'file-upload' => [
                'name' => 'File Upload',
                'file' => 'forms/file-upload.php',
                'description' => 'Drag-and-drop file upload with preview.',
                'usedOn' => ['settings', 'onboarding', 'compose', 'groups', 'listings', 'resources'],
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Input name'],
                    ['name' => 'accept', 'type' => 'string', 'default' => "'*'", 'description' => 'Accepted file types'],
                    ['name' => 'multiple', 'type' => 'bool', 'default' => 'false', 'description' => 'Allow multiple files'],
                    ['name' => 'maxSize', 'type' => 'int', 'default' => '5242880', 'description' => 'Max file size (bytes)'],
                ],
            ],
            'date-picker' => [
                'name' => 'Date Picker',
                'file' => 'forms/date-picker.php',
                'description' => 'Date input with calendar picker.',
                'usedOn' => ['compose', 'events', 'polls', 'goals', 'volunteering', 'newsletters'],
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Input name (required)'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Label text'],
                    ['name' => 'value', 'type' => 'string', 'default' => "''", 'description' => 'Date value (Y-m-d format)'],
                    ['name' => 'min', 'type' => 'string', 'default' => "''", 'description' => 'Minimum date'],
                    ['name' => 'max', 'type' => 'string', 'default' => "''", 'description' => 'Maximum date'],
                    ['name' => 'required', 'type' => 'bool', 'default' => 'false', 'description' => 'Required field'],
                    ['name' => 'format', 'type' => 'string', 'default' => "'default'", 'description' => "'default' or 'friendly'"],
                ],
            ],
            'time-picker' => [
                'name' => 'Time Picker',
                'file' => 'forms/time-picker.php',
                'description' => 'Time input with clock picker.',
                'usedOn' => ['settings', 'compose', 'events', 'polls', 'volunteering', 'newsletter scheduling'],
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Input name (required)'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Label text'],
                    ['name' => 'value', 'type' => 'string', 'default' => "''", 'description' => 'Time value (HH:MM format)'],
                    ['name' => 'min', 'type' => 'string', 'default' => "''", 'description' => 'Minimum time'],
                    ['name' => 'max', 'type' => 'string', 'default' => "''", 'description' => 'Maximum time'],
                    ['name' => 'step', 'type' => 'int', 'default' => '60', 'description' => 'Step in seconds'],
                    ['name' => 'show12Hour', 'type' => 'bool', 'default' => 'true', 'description' => 'Show 12-hour format'],
                ],
            ],
            'range-slider' => [
                'name' => 'Range Slider',
                'file' => 'forms/range-slider.php',
                'description' => 'Range/slider input for numeric values.',
                'usedOn' => ['members list', 'listings', 'matches preferences', 'admin configuration'],
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Input name (required)'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Label text'],
                    ['name' => 'value', 'type' => 'int', 'default' => '50', 'description' => 'Current value'],
                    ['name' => 'min', 'type' => 'int', 'default' => '0', 'description' => 'Minimum value'],
                    ['name' => 'max', 'type' => 'int', 'default' => '100', 'description' => 'Maximum value'],
                    ['name' => 'step', 'type' => 'int', 'default' => '1', 'description' => 'Step increment'],
                    ['name' => 'showValue', 'type' => 'bool', 'default' => 'true', 'description' => 'Show current value'],
                    ['name' => 'color', 'type' => 'string', 'default' => "'primary'", 'description' => "'primary', 'success', 'warning', 'danger'"],
                ],
            ],
            'rich-text-editor' => [
                'name' => 'Rich Text Editor',
                'file' => 'forms/rich-text-editor.php',
                'description' => 'WYSIWYG text editor wrapper.',
                'usedOn' => ['settings', 'profile edit', 'compose', 'admin newsletters', 'admin pages', 'admin blog'],
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Input name (required)'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Label text'],
                    ['name' => 'value', 'type' => 'string', 'default' => "''", 'description' => 'Content (HTML)'],
                    ['name' => 'placeholder', 'type' => 'string', 'default' => "'Start typing...'", 'description' => 'Placeholder text'],
                    ['name' => 'minHeight', 'type' => 'int', 'default' => '200', 'description' => 'Min height (px)'],
                    ['name' => 'maxHeight', 'type' => 'int', 'default' => '500', 'description' => 'Max height (px)'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'full'", 'description' => "'full', 'basic', 'minimal'"],
                ],
            ],
            'search-input' => [
                'name' => 'Search Input',
                'file' => 'forms/search-input.php',
                'description' => 'Styled search input with icon.',
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "'q'", 'description' => 'Input name'],
                    ['name' => 'value', 'type' => 'string', 'default' => "''", 'description' => 'Search value'],
                    ['name' => 'placeholder', 'type' => 'string', 'default' => "'Search...'", 'description' => 'Placeholder text'],
                    ['name' => 'autoSubmit', 'type' => 'bool', 'default' => 'true', 'description' => 'Submit on Enter'],
                ],
            ],
            'search-card' => [
                'name' => 'Search Card',
                'file' => 'forms/search-card.php',
                'description' => 'Glass search card with search input and filters.',
                'props' => [
                    ['name' => 'title', 'type' => 'string', 'default' => "'Search'", 'description' => 'Card heading'],
                    ['name' => 'count', 'type' => 'int', 'default' => '0', 'description' => 'Item count'],
                    ['name' => 'countLabel', 'type' => 'string', 'default' => "'items available'", 'description' => 'Count label'],
                    ['name' => 'action', 'type' => 'string', 'default' => "''", 'description' => 'Form action URL'],
                    ['name' => 'query', 'type' => 'string', 'default' => "''", 'description' => 'Current search query'],
                    ['name' => 'filters', 'type' => 'array', 'default' => '[]', 'description' => 'Filter configs array'],
                ],
            ],
        ],
    ],
    'buttons' => [
        'label' => 'Buttons',
        'icon' => 'hand-pointer',
        'components' => [
            'button' => [
                'name' => 'Button',
                'file' => 'buttons/button.php',
                'description' => 'Primary button component with multiple variants and states.',
                'props' => [
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Button text'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'primary'", 'description' => "'primary', 'secondary', 'outline', 'ghost', 'danger'"],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg'"],
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'FontAwesome icon'],
                    ['name' => 'iconPosition', 'type' => 'string', 'default' => "'left'", 'description' => "'left' or 'right'"],
                    ['name' => 'href', 'type' => 'string', 'default' => "''", 'description' => 'Link URL (renders as <a>)'],
                    ['name' => 'disabled', 'type' => 'bool', 'default' => 'false', 'description' => 'Disable button'],
                    ['name' => 'loading', 'type' => 'bool', 'default' => 'false', 'description' => 'Show loading state'],
                    ['name' => 'fullWidth', 'type' => 'bool', 'default' => 'false', 'description' => 'Full width button'],
                ],
            ],
            'icon-button' => [
                'name' => 'Icon Button',
                'file' => 'buttons/icon-button.php',
                'description' => 'Icon-only button with tooltip.',
                'props' => [
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'FontAwesome icon name'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Tooltip/aria-label'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'default'", 'description' => "'default', 'primary', 'danger'"],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg'"],
                ],
            ],
            'button-group' => [
                'name' => 'Button Group',
                'file' => 'buttons/button-group.php',
                'description' => 'Grouped buttons with connected styling.',
                'props' => [
                    ['name' => 'buttons', 'type' => 'array', 'default' => '[]', 'description' => 'Array of button configs'],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg'"],
                    ['name' => 'vertical', 'type' => 'bool', 'default' => 'false', 'description' => 'Stack vertically'],
                ],
            ],
            'fab' => [
                'name' => 'FAB',
                'file' => 'buttons/fab.php',
                'description' => 'Floating Action Button for primary actions.',
                'props' => [
                    ['name' => 'icon', 'type' => 'string', 'default' => "'plus'", 'description' => 'FontAwesome icon'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Tooltip text'],
                    ['name' => 'position', 'type' => 'string', 'default' => "'bottom-right'", 'description' => 'Screen position'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'primary'", 'description' => 'Color variant'],
                ],
            ],
        ],
    ],
    'feedback' => [
        'label' => 'Feedback',
        'icon' => 'message',
        'components' => [
            'alert' => [
                'name' => 'Alert',
                'file' => 'feedback/alert.php',
                'description' => 'Alert message with icon and optional dismiss button.',
                'props' => [
                    ['name' => 'type', 'type' => 'string', 'default' => "'info'", 'description' => "'info', 'success', 'warning', 'danger'"],
                    ['name' => 'message', 'type' => 'string', 'default' => "''", 'description' => 'Alert message text'],
                    ['name' => 'title', 'type' => 'string', 'default' => "''", 'description' => 'Optional title'],
                    ['name' => 'dismissible', 'type' => 'bool', 'default' => 'true', 'description' => 'Show dismiss button'],
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'Custom icon (auto if empty)'],
                ],
            ],
            'empty-state' => [
                'name' => 'Empty State',
                'file' => 'feedback/empty-state.php',
                'description' => 'Placeholder for empty content areas.',
                'props' => [
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'Icon or emoji'],
                    ['name' => 'title', 'type' => 'string', 'default' => "''", 'description' => 'Title text'],
                    ['name' => 'message', 'type' => 'string', 'default' => "''", 'description' => 'Description text'],
                    ['name' => 'action', 'type' => 'array', 'default' => 'null', 'description' => "Action button config"],
                ],
            ],
            'modal' => [
                'name' => 'Modal',
                'file' => 'feedback/modal.php',
                'description' => 'Modal dialog overlay.',
                'props' => [
                    ['name' => 'id', 'type' => 'string', 'default' => "''", 'description' => 'Modal ID for targeting'],
                    ['name' => 'title', 'type' => 'string', 'default' => "''", 'description' => 'Modal title'],
                    ['name' => 'content', 'type' => 'string', 'default' => "''", 'description' => 'Modal body content'],
                    ['name' => 'footer', 'type' => 'string', 'default' => "''", 'description' => 'Footer content'],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg', 'xl'"],
                ],
            ],
            'toast' => [
                'name' => 'Toast',
                'file' => 'feedback/toast.php',
                'description' => 'Toast notification container.',
                'props' => [
                    ['name' => 'position', 'type' => 'string', 'default' => "'top-right'", 'description' => 'Screen position'],
                ],
            ],
            'skeleton' => [
                'name' => 'Skeleton',
                'file' => 'feedback/skeleton.php',
                'description' => 'Loading skeleton placeholder.',
                'props' => [
                    ['name' => 'type', 'type' => 'string', 'default' => "'text'", 'description' => "'text', 'card', 'avatar', 'list'"],
                    ['name' => 'count', 'type' => 'int', 'default' => '1', 'description' => 'Number of skeletons'],
                    ['name' => 'animated', 'type' => 'bool', 'default' => 'true', 'description' => 'Show animation'],
                ],
            ],
            'loading-spinner' => [
                'name' => 'Loading Spinner',
                'file' => 'feedback/loading-spinner.php',
                'description' => 'Loading indicator with multiple variants.',
                'props' => [
                    ['name' => 'variant', 'type' => 'string', 'default' => "'spinner'", 'description' => "'spinner', 'dots', 'pulse'"],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg'"],
                    ['name' => 'message', 'type' => 'string', 'default' => "''", 'description' => 'Loading message'],
                ],
            ],
        ],
    ],
    'media' => [
        'label' => 'Media',
        'icon' => 'image',
        'components' => [
            'avatar' => [
                'name' => 'Avatar',
                'file' => 'media/avatar.php',
                'description' => 'User avatar with fallback to initials.',
                'props' => [
                    ['name' => 'image', 'type' => 'string', 'default' => "''", 'description' => 'Image URL'],
                    ['name' => 'name', 'type' => 'string', 'default' => "'User'", 'description' => 'User name (for initials)'],
                    ['name' => 'size', 'type' => 'int', 'default' => '40', 'description' => 'Size in pixels'],
                    ['name' => 'showRing', 'type' => 'bool', 'default' => 'false', 'description' => 'Show colored ring'],
                    ['name' => 'status', 'type' => 'string', 'default' => 'null', 'description' => "'online', 'away', 'offline'"],
                ],
            ],
            'avatar-stack' => [
                'name' => 'Avatar Stack',
                'file' => 'media/avatar-stack.php',
                'description' => 'Overlapping avatar group with overflow count.',
                'props' => [
                    ['name' => 'users', 'type' => 'array', 'default' => '[]', 'description' => 'Array of user objects'],
                    ['name' => 'max', 'type' => 'int', 'default' => '3', 'description' => 'Max visible avatars'],
                    ['name' => 'size', 'type' => 'int', 'default' => '32', 'description' => 'Avatar size'],
                ],
            ],
            'badge' => [
                'name' => 'Badge',
                'file' => 'media/badge.php',
                'description' => 'Small status badge with text and icon.',
                'props' => [
                    ['name' => 'text', 'type' => 'string', 'default' => "''", 'description' => 'Badge text'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'primary'", 'description' => "'primary', 'success', 'warning', 'danger', 'info', 'muted'"],
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'FontAwesome icon'],
                    ['name' => 'pill', 'type' => 'bool', 'default' => 'false', 'description' => 'Pill-shaped badge'],
                ],
            ],
            'icon' => [
                'name' => 'Icon',
                'file' => 'media/icon.php',
                'description' => 'FontAwesome icon with configurable size and color.',
                'props' => [
                    ['name' => 'name', 'type' => 'string', 'default' => "''", 'description' => 'Icon name (without fa-)'],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'xs', 'sm', 'md', 'lg', 'xl'"],
                    ['name' => 'color', 'type' => 'string', 'default' => "''", 'description' => 'Color variant or CSS color'],
                    ['name' => 'type', 'type' => 'string', 'default' => "'solid'", 'description' => "'solid', 'regular', 'brands'"],
                ],
            ],
            'image' => [
                'name' => 'Image',
                'file' => 'media/image.php',
                'description' => 'Responsive image with lazy loading and fallback.',
                'props' => [
                    ['name' => 'src', 'type' => 'string', 'default' => "''", 'description' => 'Image source URL'],
                    ['name' => 'alt', 'type' => 'string', 'default' => "''", 'description' => 'Alt text'],
                    ['name' => 'aspectRatio', 'type' => 'string', 'default' => "''", 'description' => "'16/9', '4/3', '1/1'"],
                    ['name' => 'lazy', 'type' => 'bool', 'default' => 'true', 'description' => 'Lazy load image'],
                ],
            ],
            'gallery' => [
                'name' => 'Gallery',
                'file' => 'media/gallery.php',
                'description' => 'Image gallery with lightbox and carousel modes.',
                'usedOn' => ['home', 'feed', 'members', 'federation', 'listings', 'blog', 'admin dashboards'],
                'props' => [
                    ['name' => 'images', 'type' => 'array', 'default' => '[]', 'description' => "Images: ['src', 'alt', 'caption']"],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'grid'", 'description' => "'grid', 'carousel', 'masonry'"],
                    ['name' => 'columns', 'type' => 'int', 'default' => '3', 'description' => 'Grid columns'],
                    ['name' => 'lightbox', 'type' => 'bool', 'default' => 'true', 'description' => 'Enable lightbox'],
                    ['name' => 'showCaptions', 'type' => 'bool', 'default' => 'false', 'description' => 'Show captions'],
                    ['name' => 'autoplay', 'type' => 'bool', 'default' => 'false', 'description' => 'Autoplay carousel'],
                ],
            ],
            'video-embed' => [
                'name' => 'Video Embed',
                'file' => 'media/video-embed.php',
                'description' => 'Embed video from YouTube, Vimeo, or direct URL.',
                'usedOn' => ['feed', 'compose', 'resources', 'admin pages builder', 'newsletter forms'],
                'props' => [
                    ['name' => 'url', 'type' => 'string', 'default' => "''", 'description' => 'Video URL'],
                    ['name' => 'title', 'type' => 'string', 'default' => "'Embedded video'", 'description' => 'Accessibility title'],
                    ['name' => 'aspectRatio', 'type' => 'string', 'default' => "'16:9'", 'description' => "'16:9', '4:3', '1:1', '21:9'"],
                    ['name' => 'autoplay', 'type' => 'bool', 'default' => 'false', 'description' => 'Autoplay video'],
                    ['name' => 'controls', 'type' => 'bool', 'default' => 'true', 'description' => 'Show controls'],
                    ['name' => 'lazy', 'type' => 'bool', 'default' => 'true', 'description' => 'Lazy load'],
                ],
            ],
            'code-block' => [
                'name' => 'Code Block',
                'file' => 'media/code-block.php',
                'description' => 'Syntax-highlighted code display with copy button.',
                'usedOn' => ['master dashboard', 'admin pages builder', 'native app', 'cron setup'],
                'props' => [
                    ['name' => 'code', 'type' => 'string', 'default' => "''", 'description' => 'Code content (required)'],
                    ['name' => 'language', 'type' => 'string', 'default' => "'text'", 'description' => 'Language for highlighting'],
                    ['name' => 'title', 'type' => 'string', 'default' => "''", 'description' => 'Block title/filename'],
                    ['name' => 'showLineNumbers', 'type' => 'bool', 'default' => 'true', 'description' => 'Show line numbers'],
                    ['name' => 'showCopy', 'type' => 'bool', 'default' => 'true', 'description' => 'Show copy button'],
                    ['name' => 'wrap', 'type' => 'bool', 'default' => 'false', 'description' => 'Wrap long lines'],
                ],
            ],
        ],
    ],
    'data' => [
        'label' => 'Data Display',
        'icon' => 'chart-bar',
        'components' => [
            'progress-bar' => [
                'name' => 'Progress Bar',
                'file' => 'data/progress-bar.php',
                'description' => 'Horizontal progress indicator.',
                'props' => [
                    ['name' => 'percent', 'type' => 'int|float', 'default' => '0', 'description' => 'Progress percentage (0-100)'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Label text'],
                    ['name' => 'showPercent', 'type' => 'bool', 'default' => 'true', 'description' => 'Show percentage text'],
                    ['name' => 'color', 'type' => 'string', 'default' => "'primary'", 'description' => "'primary', 'success', 'warning', 'danger'"],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg'"],
                    ['name' => 'striped', 'type' => 'bool', 'default' => 'false', 'description' => 'Show stripe pattern'],
                    ['name' => 'animated', 'type' => 'bool', 'default' => 'false', 'description' => 'Animate stripes'],
                ],
            ],
            'stat' => [
                'name' => 'Stat',
                'file' => 'data/stat.php',
                'description' => 'Single statistic display with trend.',
                'props' => [
                    ['name' => 'value', 'type' => 'string', 'default' => "'0'", 'description' => 'Stat value'],
                    ['name' => 'label', 'type' => 'string', 'default' => "''", 'description' => 'Stat label'],
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'FontAwesome icon'],
                    ['name' => 'trend', 'type' => 'string', 'default' => 'null', 'description' => "'up', 'down', or null"],
                    ['name' => 'trendValue', 'type' => 'string', 'default' => "''", 'description' => 'Change value'],
                ],
            ],
            'leaderboard' => [
                'name' => 'Leaderboard',
                'file' => 'data/leaderboard.php',
                'description' => 'Ranked list with user avatars and scores.',
                'props' => [
                    ['name' => 'users', 'type' => 'array', 'default' => '[]', 'description' => 'Array of user data'],
                    ['name' => 'metric', 'type' => 'string', 'default' => "'points'", 'description' => 'Metric label'],
                    ['name' => 'highlightUserId', 'type' => 'int', 'default' => 'null', 'description' => 'User ID to highlight'],
                    ['name' => 'limit', 'type' => 'int', 'default' => '10', 'description' => 'Max entries to show'],
                ],
            ],
            'table' => [
                'name' => 'Table',
                'file' => 'data/table.php',
                'description' => 'Data table with optional sorting and styling.',
                'props' => [
                    ['name' => 'headers', 'type' => 'array', 'default' => '[]', 'description' => "Array of header configs"],
                    ['name' => 'rows', 'type' => 'array', 'default' => '[]', 'description' => 'Array of row data'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'default'", 'description' => "'default', 'striped', 'bordered'"],
                    ['name' => 'hoverable', 'type' => 'bool', 'default' => 'true', 'description' => 'Add hover effect'],
                    ['name' => 'compact', 'type' => 'bool', 'default' => 'false', 'description' => 'Compact spacing'],
                ],
            ],
            'list' => [
                'name' => 'List',
                'file' => 'data/list.php',
                'description' => 'Styled list with icons and actions.',
                'props' => [
                    ['name' => 'items', 'type' => 'array', 'default' => '[]', 'description' => 'List items array'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'default'", 'description' => "'default', 'divided', 'compact'"],
                    ['name' => 'hoverable', 'type' => 'bool', 'default' => 'true', 'description' => 'Add hover effect'],
                ],
            ],
            'timeline-item' => [
                'name' => 'Timeline Item',
                'file' => 'data/timeline-item.php',
                'description' => 'Single item in a vertical timeline.',
                'usedOn' => ['admin/activity_log', 'organizations/audit-log', 'federation/activity'],
                'props' => [
                    ['name' => 'icon', 'type' => 'string', 'default' => "''", 'description' => 'FontAwesome icon'],
                    ['name' => 'title', 'type' => 'string', 'default' => "''", 'description' => 'Item title'],
                    ['name' => 'content', 'type' => 'string', 'default' => "''", 'description' => 'Item content'],
                    ['name' => 'time', 'type' => 'string', 'default' => "''", 'description' => 'Timestamp'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'default'", 'description' => "'default', 'success', 'warning'"],
                ],
            ],
        ],
    ],
    'interactive' => [
        'label' => 'Interactive',
        'icon' => 'hand-sparkles',
        'components' => [
            'accordion' => [
                'name' => 'Accordion',
                'file' => 'interactive/accordion.php',
                'description' => 'Collapsible accordion sections.',
                'usedOn' => ['FAQ pages', 'settings', 'badge showcases', 'form sections'],
                'props' => [
                    ['name' => 'items', 'type' => 'array', 'default' => '[]', 'description' => "Array of ['id', 'title', 'content', 'icon', 'expanded']"],
                    ['name' => 'allowMultiple', 'type' => 'bool', 'default' => 'false', 'description' => 'Allow multiple open sections'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'default'", 'description' => "'default', 'bordered', 'separated'"],
                ],
            ],
            'tooltip' => [
                'name' => 'Tooltip',
                'file' => 'interactive/tooltip.php',
                'description' => 'Hover tooltip for additional info.',
                'usedOn' => ['messages', 'admin dashboard', 'federation analytics', 'pages builder'],
                'props' => [
                    ['name' => 'content', 'type' => 'string', 'default' => "''", 'description' => 'Tooltip content'],
                    ['name' => 'position', 'type' => 'string', 'default' => "'top'", 'description' => "'top', 'bottom', 'left', 'right'"],
                    ['name' => 'trigger', 'type' => 'string', 'default' => "''", 'description' => 'Trigger element HTML'],
                ],
            ],
            'copy-button' => [
                'name' => 'Copy Button',
                'file' => 'interactive/copy-button.php',
                'description' => 'Button to copy text to clipboard.',
                'usedOn' => ['post cards', 'blog', 'admin settings', 'pages builder', 'auth login'],
                'props' => [
                    ['name' => 'text', 'type' => 'string', 'default' => "''", 'description' => 'Text to copy'],
                    ['name' => 'label', 'type' => 'string', 'default' => "'Copy'", 'description' => 'Button label'],
                    ['name' => 'successLabel', 'type' => 'string', 'default' => "'Copied!'", 'description' => 'Success message'],
                ],
            ],
            'share-button' => [
                'name' => 'Share Button',
                'file' => 'interactive/share-button.php',
                'description' => 'Social sharing button with dropdown.',
                'usedOn' => ['post cards', 'blog', 'pages builder', 'auth login'],
                'props' => [
                    ['name' => 'url', 'type' => 'string', 'default' => "''", 'description' => 'URL to share'],
                    ['name' => 'title', 'type' => 'string', 'default' => "''", 'description' => 'Share title'],
                    ['name' => 'networks', 'type' => 'array', 'default' => "['twitter', 'facebook', 'linkedin']", 'description' => 'Social networks'],
                ],
            ],
            'star-rating' => [
                'name' => 'Star Rating',
                'file' => 'interactive/star-rating.php',
                'description' => 'Interactive star rating input.',
                'usedOn' => ['reviews/create', 'federation/review-form', 'profile reviews'],
                'props' => [
                    ['name' => 'value', 'type' => 'int', 'default' => '0', 'description' => 'Current rating (0-5)'],
                    ['name' => 'readonly', 'type' => 'bool', 'default' => 'false', 'description' => 'Read-only mode'],
                    ['name' => 'size', 'type' => 'string', 'default' => "'md'", 'description' => "'sm', 'md', 'lg'"],
                ],
            ],
            'poll-voting' => [
                'name' => 'Poll Voting',
                'file' => 'interactive/poll-voting.php',
                'description' => 'Poll with voting options and results.',
                'usedOn' => ['polls/show', 'polls embedded in feed'],
                'props' => [
                    ['name' => 'options', 'type' => 'array', 'default' => '[]', 'description' => 'Poll options array'],
                    ['name' => 'showResults', 'type' => 'bool', 'default' => 'false', 'description' => 'Show results'],
                    ['name' => 'userVote', 'type' => 'int', 'default' => 'null', 'description' => 'User\'s vote ID'],
                ],
            ],
            'draggable-list' => [
                'name' => 'Draggable List',
                'file' => 'interactive/draggable-list.php',
                'description' => 'Sortable/draggable list for reordering items.',
                'usedOn' => ['admin menus builder', 'pages create', 'newsletters', 'blog builder'],
                'props' => [
                    ['name' => 'items', 'type' => 'array', 'default' => '[]', 'description' => "Items: ['id', 'content', 'data']"],
                    ['name' => 'name', 'type' => 'string', 'default' => "'order'", 'description' => 'Hidden input name'],
                    ['name' => 'showHandle', 'type' => 'bool', 'default' => 'true', 'description' => 'Show drag handle'],
                    ['name' => 'showRemove', 'type' => 'bool', 'default' => 'false', 'description' => 'Show remove button'],
                    ['name' => 'variant', 'type' => 'string', 'default' => "'default'", 'description' => "'default', 'cards', 'compact'"],
                ],
            ],
        ],
    ],
    'social' => [
        'label' => 'Social',
        'icon' => 'users',
        'components' => [
            'comment-section' => [
                'name' => 'Comment Section',
                'file' => 'social/comment-section.php',
                'description' => 'Complete comment section with form and list.',
                'usedOn' => ['feed/show', 'polls/show', 'events/show', 'listings/show', 'volunteering/show', 'goals/show', 'groups/show'],
                'props' => [
                    ['name' => 'contentType', 'type' => 'string', 'default' => "'post'", 'description' => 'Content type identifier'],
                    ['name' => 'contentId', 'type' => 'int', 'default' => '0', 'description' => 'Content ID'],
                    ['name' => 'comments', 'type' => 'array', 'default' => '[]', 'description' => 'Comments array'],
                    ['name' => 'currentUser', 'type' => 'array', 'default' => '[]', 'description' => 'Current user data'],
                    ['name' => 'allowReplies', 'type' => 'bool', 'default' => 'true', 'description' => 'Allow nested replies'],
                ],
            ],
            'notification-item' => [
                'name' => 'Notification Item',
                'file' => 'social/notification-item.php',
                'description' => 'Single notification list item.',
                'usedOn' => ['notifications/index', 'dashboard notifications'],
                'props' => [
                    ['name' => 'notification', 'type' => 'array', 'default' => '[]', 'description' => 'Notification data'],
                    ['name' => 'unread', 'type' => 'bool', 'default' => 'false', 'description' => 'Unread state'],
                ],
            ],
            'profile-header' => [
                'name' => 'Profile Header',
                'file' => 'social/profile-header.php',
                'description' => 'User profile header with stats and actions.',
                'usedOn' => ['profile/show', 'federation/member-profile', 'federation/partner-profile'],
                'props' => [
                    ['name' => 'user', 'type' => 'array', 'default' => '[]', 'description' => 'User data object'],
                    ['name' => 'isOwn', 'type' => 'bool', 'default' => 'false', 'description' => 'Is own profile'],
                    ['name' => 'stats', 'type' => 'array', 'default' => '[]', 'description' => 'Profile stats'],
                ],
            ],
        ],
    ],
    'shared' => [
        'label' => 'Shared',
        'icon' => 'share-nodes',
        'components' => [
            'accessibility-helpers' => [
                'name' => 'Accessibility Helpers',
                'file' => 'shared/accessibility-helpers.php',
                'description' => 'Reusable accessibility utilities (functions file).',
                'props' => [
                    ['name' => 'renderSkipLink()', 'type' => 'function', 'default' => '-', 'description' => 'Renders skip to main content link'],
                    ['name' => 'srOnly($text)', 'type' => 'function', 'default' => '-', 'description' => 'Returns screen reader only span'],
                    ['name' => 'iconButton(...)', 'type' => 'function', 'default' => '-', 'description' => 'Renders accessible icon button'],
                    ['name' => 'iconLink(...)', 'type' => 'function', 'default' => '-', 'description' => 'Renders accessible icon link'],
                ],
            ],
            'post-card' => [
                'name' => 'Post Card',
                'file' => 'shared/post-card.php',
                'description' => 'Reusable post display for feed, profile views.',
                'props' => [
                    ['name' => 'post', 'type' => 'array', 'default' => '[]', 'description' => 'Post data (required)'],
                    ['name' => 'postAuthor', 'type' => 'array', 'default' => '[]', 'description' => 'Author data (required)'],
                    ['name' => 'currentUserId', 'type' => 'int', 'default' => 'session', 'description' => 'Current user ID'],
                    ['name' => 'showActions', 'type' => 'bool', 'default' => 'true', 'description' => 'Show like/comment/share'],
                ],
            ],
        ],
    ],
    'nexus' => [
        'label' => 'Nexus Score',
        'icon' => 'star',
        'components' => [
            'achievement-showcase' => [
                'name' => 'Achievement Showcase',
                'file' => 'achievement-showcase.php',
                'description' => 'Visual display of badges, achievements, milestones.',
                'props' => [
                    ['name' => 'badges', 'type' => 'array', 'default' => '[]', 'description' => "User's earned badges"],
                    ['name' => 'milestones', 'type' => 'array', 'default' => '[]', 'description' => 'Completed milestones'],
                    ['name' => 'recentAchievements', 'type' => 'array', 'default' => '[]', 'description' => 'Recent achievements'],
                    ['name' => 'isPublic', 'type' => 'bool', 'default' => 'false', 'description' => 'Public profile view'],
                ],
            ],
            'nexus-leaderboard' => [
                'name' => 'Nexus Leaderboard',
                'file' => 'nexus-leaderboard.php',
                'description' => 'Community ranking and comparison features.',
                'props' => [
                    ['name' => 'leaderboardData', 'type' => 'array', 'default' => '[]', 'description' => 'Top users/orgs by score'],
                    ['name' => 'currentUserData', 'type' => 'array', 'default' => '[]', 'description' => "Current user's rank/score"],
                    ['name' => 'timeframe', 'type' => 'string', 'default' => "'all-time'", 'description' => "'weekly', 'monthly', 'all-time'"],
                    ['name' => 'category', 'type' => 'string', 'default' => "'overall'", 'description' => "'overall', 'engagement', etc."],
                ],
            ],
            'nexus-score-widget' => [
                'name' => 'Nexus Score Widget',
                'file' => 'nexus-score-widget.php',
                'description' => 'Compact widget for profile score display.',
                'props' => [
                    ['name' => 'userId', 'type' => 'int', 'default' => 'session', 'description' => 'User ID to display'],
                    ['name' => 'tenantId', 'type' => 'int', 'default' => 'session', 'description' => 'Tenant ID'],
                    ['name' => 'isOwner', 'type' => 'bool', 'default' => 'false', 'description' => 'Viewing own profile'],
                ],
            ],
            'nexus-score-dashboard' => [
                'name' => 'Nexus Score Dashboard',
                'file' => 'nexus-score-dashboard.php',
                'description' => 'Full dashboard with score breakdown.',
                'props' => [
                    ['name' => 'userId', 'type' => 'int', 'default' => 'session', 'description' => 'User ID'],
                    ['name' => 'tenantId', 'type' => 'int', 'default' => 'session', 'description' => 'Tenant ID'],
                ],
            ],
            'nexus-score-charts' => [
                'name' => 'Nexus Score Charts',
                'file' => 'nexus-score-charts.php',
                'description' => 'Score visualization with charts.',
                'props' => [
                    ['name' => 'scoreData', 'type' => 'array', 'default' => '[]', 'description' => 'Score data for charts'],
                ],
            ],
            'org-ui-components' => [
                'name' => 'Org UI Components',
                'file' => 'org-ui-components.php',
                'description' => 'Shared UI: modals, toasts, loaders for organizations.',
                'props' => [
                    ['name' => '-', 'type' => 'includes', 'default' => '-', 'description' => 'Include once in layout for modal/toast system'],
                ],
            ],
        ],
    ],
];

// Get current component from URL
$currentCategory = $_GET['category'] ?? 'layout';
$currentComponent = $_GET['component'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Component Documentation - Project NEXUS</title>
    <link rel="stylesheet" href="/assets/css/design-tokens.css">
    <link rel="stylesheet" href="/assets/css/modern/main.css">
    <link rel="stylesheet" href="/assets/css/modern/components-library.css">
    <link rel="stylesheet" href="/assets/css/modern/preview.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <style>
        /* Storybook Layout */
        .storybook {
            display: flex;
            min-height: 100vh;
        }

        .storybook__sidebar {
            width: 280px;
            background: var(--color-surface, #fff);
            border-right: 1px solid var(--color-border, #e5e5e5);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 100;
        }

        .storybook__main {
            flex: 1;
            margin-left: 280px;
            padding: var(--space-8, 32px);
            background: var(--color-background, #f5f5f5);
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .storybook__logo {
            padding: var(--space-5, 20px);
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: var(--space-3, 12px);
        }

        .storybook__logo-icon {
            width: 36px;
            height: 36px;
            background: var(--color-primary-500);
            border-radius: var(--radius-lg, 8px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .storybook__logo-text {
            font-weight: 600;
            font-size: 1rem;
        }

        .storybook__logo-version {
            font-size: 0.75rem;
            color: var(--color-text-muted);
        }

        .storybook__search {
            padding: var(--space-4, 16px);
            border-bottom: 1px solid var(--color-border);
        }

        .storybook__search-input {
            width: 100%;
            padding: var(--space-2, 8px) var(--space-3, 12px);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md, 6px);
            font-size: 0.875rem;
            background: var(--color-background);
        }

        .storybook__search-input:focus {
            outline: none;
            border-color: var(--color-primary-500);
            box-shadow: 0 0 0 3px var(--color-primary-100);
        }

        .storybook__nav {
            padding: var(--space-4, 16px) 0;
        }

        .storybook__category {
            margin-bottom: var(--space-2, 8px);
        }

        .storybook__category-header {
            display: flex;
            align-items: center;
            gap: var(--space-2, 8px);
            padding: var(--space-2, 8px) var(--space-4, 16px);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-muted);
            cursor: pointer;
            user-select: none;
        }

        .storybook__category-header:hover {
            color: var(--color-text);
        }

        .storybook__category-icon {
            width: 18px;
            text-align: center;
        }

        .storybook__category-count {
            margin-left: auto;
            background: var(--color-surface-alt);
            padding: 2px 6px;
            border-radius: var(--radius-sm);
            font-size: 0.625rem;
        }

        .storybook__component-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .storybook__component-item {
            padding: var(--space-2, 8px) var(--space-4, 16px) var(--space-2, 8px) var(--space-8, 32px);
            font-size: 0.875rem;
            color: var(--color-text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
            border-left: 2px solid transparent;
        }

        .storybook__component-item:hover {
            background: var(--color-surface-alt);
            color: var(--color-text);
        }

        .storybook__component-item--active {
            background: var(--color-primary-50);
            color: var(--color-primary-700);
            border-left-color: var(--color-primary-500);
            font-weight: 500;
        }

        .storybook__component-item-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-2, 8px);
        }

        .storybook__shared-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 0.625rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: var(--radius-sm);
            white-space: nowrap;
        }

        .storybook__shared-badge i {
            font-size: 0.5rem;
        }

        /* Green badge for components in shared/ folder */
        .storybook__shared-badge--folder {
            color: var(--color-success-700);
            background: var(--color-success-50);
        }

        /* Blue badge for components used in multiple places */
        .storybook__shared-badge--usage {
            color: var(--color-primary-700);
            background: var(--color-primary-50);
        }

        /* Main Content Styles */
        .storybook__header {
            margin-bottom: var(--space-8, 32px);
        }

        .storybook__title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: var(--space-2, 8px);
        }

        .storybook__description {
            font-size: 1.125rem;
            color: var(--color-text-muted);
            max-width: 600px;
        }

        .storybook__breadcrumb {
            font-size: 0.875rem;
            color: var(--color-text-muted);
            margin-bottom: var(--space-4, 16px);
        }

        .storybook__breadcrumb a {
            color: var(--color-primary-600);
            text-decoration: none;
        }

        /* Canvas Section */
        .storybook__canvas {
            background: var(--color-surface);
            border-radius: var(--radius-xl, 12px);
            border: 1px solid var(--color-border);
            overflow: hidden;
            margin-bottom: var(--space-6, 24px);
        }

        .storybook__canvas-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-3, 12px) var(--space-4, 16px);
            background: var(--color-surface-alt);
            border-bottom: 1px solid var(--color-border);
        }

        .storybook__canvas-title {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .storybook__canvas-tabs {
            display: flex;
            gap: var(--space-1, 4px);
        }

        .storybook__canvas-tab {
            padding: var(--space-1, 4px) var(--space-3, 12px);
            font-size: 0.75rem;
            border: none;
            background: transparent;
            color: var(--color-text-muted);
            cursor: pointer;
            border-radius: var(--radius-md);
            transition: all 0.15s ease;
        }

        .storybook__canvas-tab:hover {
            background: var(--color-surface);
        }

        .storybook__canvas-tab--active {
            background: var(--color-surface);
            color: var(--color-text);
            font-weight: 500;
        }

        .storybook__canvas-preview {
            padding: var(--space-8, 32px);
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .storybook__canvas-preview--left {
            justify-content: flex-start;
        }

        .storybook__canvas-code {
            display: none;
            padding: 0;
            margin: 0;
            overflow-x: auto;
        }

        .storybook__canvas-code--active {
            display: block;
        }

        .storybook__canvas-code pre {
            margin: 0;
            padding: var(--space-4, 16px);
            font-size: 0.875rem;
            line-height: 1.6;
        }

        /* Props Table */
        .storybook__props {
            background: var(--color-surface);
            border-radius: var(--radius-xl, 12px);
            border: 1px solid var(--color-border);
            overflow: hidden;
            margin-bottom: var(--space-6, 24px);
        }

        .storybook__props-header {
            padding: var(--space-4, 16px);
            background: var(--color-surface-alt);
            border-bottom: 1px solid var(--color-border);
            font-weight: 600;
        }

        .storybook__props-table {
            width: 100%;
            border-collapse: collapse;
        }

        .storybook__props-table th,
        .storybook__props-table td {
            padding: var(--space-3, 12px) var(--space-4, 16px);
            text-align: left;
            border-bottom: 1px solid var(--color-border);
            font-size: 0.875rem;
        }

        .storybook__props-table th {
            font-weight: 600;
            color: var(--color-text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--color-surface-alt);
        }

        .storybook__props-table tr:last-child td {
            border-bottom: none;
        }

        .storybook__prop-name {
            font-family: monospace;
            color: var(--color-primary-600);
            font-weight: 500;
        }

        .storybook__prop-type {
            font-family: monospace;
            color: var(--color-warning-600);
            font-size: 0.8125rem;
        }

        .storybook__prop-default {
            font-family: monospace;
            color: var(--color-text-muted);
            font-size: 0.8125rem;
        }

        .storybook__prop-required {
            color: var(--color-danger-500);
            font-size: 0.75rem;
            margin-left: var(--space-1);
        }

        /* Variants Grid */
        .storybook__variants {
            background: var(--color-surface);
            border-radius: var(--radius-xl, 12px);
            border: 1px solid var(--color-border);
            overflow: hidden;
            margin-bottom: var(--space-6, 24px);
        }

        .storybook__used-on {
            background: var(--color-surface);
            border-radius: var(--radius-lg, 8px);
            border: 1px solid var(--color-border);
            overflow: hidden;
            margin-bottom: var(--space-6, 24px);
        }

        .storybook__used-on-header {
            padding: var(--space-3, 12px) var(--space-4, 16px);
            background: var(--color-success-50);
            border-bottom: 1px solid var(--color-success-100);
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--color-success-700);
            display: flex;
            align-items: center;
            gap: var(--space-2, 8px);
        }

        .storybook__used-on-list {
            padding: var(--space-4, 16px);
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2, 8px);
        }

        .storybook__used-on-tag {
            display: inline-flex;
            align-items: center;
            padding: var(--space-1, 4px) var(--space-3, 12px);
            background: var(--color-surface-alt);
            border-radius: var(--radius-full, 9999px);
            font-size: 0.8125rem;
            color: var(--color-text-muted);
        }

        .storybook__variants-header {
            padding: var(--space-4, 16px);
            background: var(--color-surface-alt);
            border-bottom: 1px solid var(--color-border);
            font-weight: 600;
        }

        .storybook__variants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--space-4, 16px);
            padding: var(--space-6, 24px);
        }

        .storybook__variant-item {
            text-align: center;
        }

        .storybook__variant-label {
            font-size: 0.75rem;
            color: var(--color-text-muted);
            margin-top: var(--space-2, 8px);
        }

        /* Welcome Page */
        .storybook__welcome {
            max-width: 800px;
        }

        .storybook__welcome h1 {
            font-size: 2.5rem;
            margin-bottom: var(--space-4);
        }

        .storybook__welcome p {
            font-size: 1.125rem;
            color: var(--color-text-muted);
            margin-bottom: var(--space-6);
        }

        .storybook__stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-4);
            margin-bottom: var(--space-8);
        }

        .storybook__stat-card {
            background: var(--color-surface);
            padding: var(--space-5);
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-border);
            text-align: center;
        }

        .storybook__stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-primary-600);
        }

        .storybook__stat-label {
            font-size: 0.875rem;
            color: var(--color-text-muted);
        }

        .storybook__quick-start {
            background: var(--color-surface);
            padding: var(--space-6);
            border-radius: var(--radius-xl);
            border: 1px solid var(--color-border);
        }

        .storybook__quick-start h2 {
            margin-bottom: var(--space-4);
        }

        .storybook__quick-start pre {
            background: var(--color-gray-900);
            color: var(--color-gray-100);
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            overflow-x: auto;
            font-size: 0.875rem;
        }

        .storybook__quick-start-subheading {
            margin-top: var(--space-6);
        }

        .storybook__category-link {
            text-decoration: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .storybook__sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .storybook__sidebar--open {
                transform: translateX(0);
            }

            .storybook__main {
                margin-left: 0;
            }

            .storybook__stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body class="preview-page">
    <div class="storybook">
        <!-- Sidebar -->
        <aside class="storybook__sidebar">
            <div class="storybook__logo">
                <div class="storybook__logo-icon">
                    <i class="fa-solid fa-cubes"></i>
                </div>
                <div>
                    <div class="storybook__logo-text">Component Library</div>
                    <div class="storybook__logo-version">v1.0.0 - Modern Theme</div>
                </div>
            </div>

            <div class="storybook__search">
                <input
                    type="text"
                    class="storybook__search-input"
                    placeholder="Search components..."
                    id="componentSearch"
                    oninput="filterComponents(this.value)"
                >
            </div>

            <nav class="storybook__nav">
                <a href="?category=welcome" class="storybook__category-header storybook__category-link">
                    <span class="storybook__category-icon"><i class="fa-solid fa-home"></i></span>
                    <span>Welcome</span>
                </a>

                <?php foreach ($componentRegistry as $categoryId => $category): ?>
                    <div class="storybook__category" data-category="<?= e($categoryId) ?>">
                        <div class="storybook__category-header" onclick="toggleCategory('<?= e($categoryId) ?>')">
                            <span class="storybook__category-icon"><i class="fa-solid fa-<?= e($category['icon']) ?>"></i></span>
                            <span><?= e($category['label']) ?></span>
                            <span class="storybook__category-count"><?= count($category['components']) ?></span>
                        </div>
                        <ul class="storybook__component-list" id="category-<?= e($categoryId) ?>">
                            <?php foreach ($category['components'] as $componentId => $component): ?>
                                <?php
                                $isFromSharedFolder = strpos($component['file'], 'shared/') === 0;
                                $hasMultipleUses = !empty($component['usedOn']) && count($component['usedOn']) >= 3;
                                ?>
                                <li
                                    class="storybook__component-item <?= ($currentCategory === $categoryId && $currentComponent === $componentId) ? 'storybook__component-item--active' : '' ?>"
                                    onclick="window.location.href='?category=<?= e($categoryId) ?>&component=<?= e($componentId) ?>'"
                                    data-component="<?= e($componentId) ?>"
                                    data-name="<?= e(strtolower($component['name'])) ?>"
                                >
                                    <span class="storybook__component-item-wrapper">
                                        <span><?= e($component['name']) ?></span>
                                        <?php if ($isFromSharedFolder): ?>
                                        <span class="storybook__shared-badge storybook__shared-badge--folder" title="From shared/ folder - designed for reuse across pages">
                                            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                                            Shared
                                        </span>
                                        <?php elseif ($hasMultipleUses): ?>
                                        <span class="storybook__shared-badge storybook__shared-badge--usage" title="Used in <?= count($component['usedOn']) ?> places: <?= e(implode(', ', $component['usedOn'])) ?>">
                                            <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                                            <?= count($component['usedOn']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="storybook__main">
            <?php if ($currentCategory === 'welcome' || empty($currentComponent)): ?>
                <!-- Welcome Page -->
                <div class="storybook__welcome">
                    <h1>Project NEXUS Component Library</h1>
                    <p>A comprehensive collection of reusable UI components for the Modern theme. Built with accessibility, consistency, and developer experience in mind.</p>

                    <div class="storybook__stats-grid">
                        <?php
                        $totalComponents = 0;
                        foreach ($componentRegistry as $cat) {
                            $totalComponents += count($cat['components']);
                        }
                        ?>
                        <div class="storybook__stat-card">
                            <div class="storybook__stat-value"><?= $totalComponents ?></div>
                            <div class="storybook__stat-label">Components</div>
                        </div>
                        <div class="storybook__stat-card">
                            <div class="storybook__stat-value"><?= count($componentRegistry) ?></div>
                            <div class="storybook__stat-label">Categories</div>
                        </div>
                        <div class="storybook__stat-card">
                            <div class="storybook__stat-value">100%</div>
                            <div class="storybook__stat-label">Documented</div>
                        </div>
                        <div class="storybook__stat-card">
                            <div class="storybook__stat-value">A</div>
                            <div class="storybook__stat-label">WCAG Grade</div>
                        </div>
                    </div>

                    <div class="storybook__quick-start">
                        <h2>Quick Start</h2>
                        <p>Include any component using PHP's include with variable scope:</p>
                        <pre><code class="language-php">&lt;?php
// Include the component init helper
require_once __DIR__ . '/views/modern/components/_init.php';

// Set component props
$label = 'Click Me';
$variant = 'primary';
$icon = 'arrow-right';

// Include the component
include __DIR__ . '/views/modern/components/buttons/button.php';
?&gt;</code></pre>

                        <h3 class="storybook__quick-start-subheading">Using the component() Helper</h3>
                        <pre><code class="language-php">&lt;?php
// Render a component with props
component('buttons/button', [
    'label' => 'Click Me',
    'variant' => 'primary',
    'icon' => 'arrow-right'
]);
?&gt;</code></pre>
                    </div>
                </div>

            <?php elseif (isset($componentRegistry[$currentCategory]['components'][$currentComponent])): ?>
                <?php
                $componentData = $componentRegistry[$currentCategory]['components'][$currentComponent];
                $categoryData = $componentRegistry[$currentCategory];
                ?>

                <!-- Component Documentation -->
                <div class="storybook__breadcrumb">
                    <a href="?category=welcome">Components</a> / <?= e($categoryData['label']) ?> / <?= e($componentData['name']) ?>
                </div>

                <header class="storybook__header">
                    <h1 class="storybook__title"><?= e($componentData['name']) ?></h1>
                    <p class="storybook__description"><?= e($componentData['description']) ?></p>
                </header>

                <!-- Canvas -->
                <div class="storybook__canvas">
                    <div class="storybook__canvas-header">
                        <span class="storybook__canvas-title">Preview</span>
                        <div class="storybook__canvas-tabs">
                            <button class="storybook__canvas-tab storybook__canvas-tab--active" onclick="showTab(this, 'preview')">
                                <i class="fa-solid fa-eye"></i> Preview
                            </button>
                            <button class="storybook__canvas-tab" onclick="showTab(this, 'code')">
                                <i class="fa-solid fa-code"></i> Code
                            </button>
                        </div>
                    </div>

                    <div class="storybook__canvas-preview storybook__canvas-preview--left" id="canvas-preview">
                        <?php
                        // Render component preview with sample data
                        $componentFile = __DIR__ . '/' . $componentData['file'];
                        if (file_exists($componentFile)) {
                            // Set up sample props based on component type
                            switch ($currentComponent) {
                                case 'button':
                                    $label = 'Click Me';
                                    $variant = 'primary';
                                    $icon = 'arrow-right';
                                    break;
                                case 'alert':
                                    $type = 'info';
                                    $message = 'This is an informational alert message.';
                                    $dismissible = true;
                                    break;
                                case 'avatar':
                                    $name = 'Jane Smith';
                                    $image = '';
                                    $size = 48;
                                    $status = 'online';
                                    break;
                                case 'badge':
                                    $text = 'New';
                                    $variant = 'success';
                                    $icon = 'sparkles';
                                    $pill = true;
                                    break;
                                case 'progress-bar':
                                    $percent = 65;
                                    $label = 'Progress';
                                    $color = 'primary';
                                    break;
                                case 'input':
                                    $name = 'email';
                                    $type = 'email';
                                    $placeholder = 'you@example.com';
                                    $icon = 'envelope';
                                    break;
                                case 'tabs':
                                    $tabs = [
                                        ['id' => 'tab1', 'label' => 'Tab 1', 'icon' => 'home'],
                                        ['id' => 'tab2', 'label' => 'Tab 2', 'count' => 5],
                                        ['id' => 'tab3', 'label' => 'Tab 3'],
                                    ];
                                    $activeTab = 'tab1';
                                    break;
                                case 'breadcrumb':
                                    $items = [
                                        ['label' => 'Home', 'href' => '#'],
                                        ['label' => 'Category', 'href' => '#'],
                                        ['label' => 'Current Page'],
                                    ];
                                    break;
                                case 'empty-state':
                                    $icon = '🔍';
                                    $title = 'No results found';
                                    $message = 'Try adjusting your search criteria.';
                                    $action = ['label' => 'Clear Search', 'href' => '#'];
                                    break;
                                default:
                                    // Use defaults from component
                                    break;
                            }
                            include $componentFile;
                        } else {
                            echo '<p class="preview-section__placeholder">Component file not found</p>';
                        }
                        ?>
                    </div>

                    <div class="storybook__canvas-code" id="canvas-code">
                        <pre><code class="language-php">&lt;?php
<?php
// Generate sample code
echo "// Include component\n";
foreach ($componentData['props'] as $prop) {
    $default = $prop['default'];
    echo "\${$prop['name']} = {$default};\n";
}
echo "\ninclude __DIR__ . '/views/modern/components/{$componentData['file']}';\n";
?>
?&gt;</code></pre>
                    </div>
                </div>

                <!-- Props Table -->
                <div class="storybook__props">
                    <div class="storybook__props-header">
                        <i class="fa-solid fa-sliders"></i> Props / API
                    </div>
                    <table class="storybook__props-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Default</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($componentData['props'] as $prop): ?>
                                <tr>
                                    <td>
                                        <span class="storybook__prop-name">$<?= e($prop['name']) ?></span>
                                    </td>
                                    <td><span class="storybook__prop-type"><?= e($prop['type']) ?></span></td>
                                    <td><span class="storybook__prop-default"><?= e($prop['default']) ?></span></td>
                                    <td><?= e($prop['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Used On Section -->
                <?php if (!empty($componentData['usedOn'])): ?>
                <div class="storybook__used-on">
                    <div class="storybook__used-on-header">
                        <i class="fa-solid fa-share-nodes"></i> Used on <?= count($componentData['usedOn']) ?> pages
                    </div>
                    <div class="storybook__used-on-list">
                        <?php foreach ($componentData['usedOn'] as $location): ?>
                            <span class="storybook__used-on-tag"><?= e($location) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Variants (for components with variants) -->
                <?php if (in_array($currentComponent, ['button', 'alert', 'badge', 'avatar'])): ?>
                <div class="storybook__variants">
                    <div class="storybook__variants-header">
                        <i class="fa-solid fa-palette"></i> Variants
                    </div>
                    <div class="storybook__variants-grid">
                        <?php
                        switch ($currentComponent) {
                            case 'button':
                                foreach (['primary', 'secondary', 'outline', 'ghost', 'danger'] as $v) {
                                    echo '<div class="storybook__variant-item">';
                                    $variant = $v;
                                    $label = ucfirst($v);
                                    $icon = '';
                                    include __DIR__ . '/buttons/button.php';
                                    echo '<div class="storybook__variant-label">' . ucfirst($v) . '</div>';
                                    echo '</div>';
                                }
                                break;
                            case 'alert':
                                foreach (['info', 'success', 'warning', 'danger'] as $t) {
                                    echo '<div class="storybook__variant-item">';
                                    $type = $t;
                                    $message = ucfirst($t) . ' message';
                                    $dismissible = false;
                                    $title = '';
                                    include __DIR__ . '/feedback/alert.php';
                                    echo '</div>';
                                }
                                break;
                            case 'badge':
                                foreach (['primary', 'success', 'warning', 'danger', 'info', 'muted'] as $v) {
                                    echo '<div class="storybook__variant-item">';
                                    $variant = $v;
                                    $text = ucfirst($v);
                                    $icon = '';
                                    $pill = false;
                                    include __DIR__ . '/media/badge.php';
                                    echo '<div class="storybook__variant-label">' . ucfirst($v) . '</div>';
                                    echo '</div>';
                                }
                                break;
                            case 'avatar':
                                foreach ([24, 32, 40, 48, 64] as $s) {
                                    echo '<div class="storybook__variant-item">';
                                    $size = $s;
                                    $name = 'User';
                                    $image = '';
                                    $status = null;
                                    $showRing = false;
                                    include __DIR__ . '/media/avatar.php';
                                    echo '<div class="storybook__variant-label">' . $s . 'px</div>';
                                    echo '</div>';
                                }
                                break;
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <p>Component not found. Please select a component from the sidebar.</p>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        // Initialize syntax highlighting
        hljs.highlightAll();

        // Toggle category collapse
        function toggleCategory(categoryId) {
            const list = document.getElementById('category-' + categoryId);
            if (list) {
                list.classList.toggle('component-hidden');
            }
        }

        // Show tab content
        function showTab(button, tabName) {
            const canvas = button.closest('.storybook__canvas');
            const tabs = canvas.querySelectorAll('.storybook__canvas-tab');
            const preview = canvas.querySelector('.storybook__canvas-preview');
            const code = canvas.querySelector('.storybook__canvas-code');

            tabs.forEach(t => t.classList.remove('storybook__canvas-tab--active'));
            button.classList.add('storybook__canvas-tab--active');

            if (tabName === 'preview') {
                preview.classList.remove('component-hidden');
                code.classList.remove('storybook__canvas-code--active');
            } else {
                preview.classList.add('component-hidden');
                code.classList.add('storybook__canvas-code--active');
            }
        }

        // Filter components by search
        function filterComponents(query) {
            query = query.toLowerCase().trim();
            const items = document.querySelectorAll('.storybook__component-item');

            items.forEach(item => {
                const name = item.dataset.name || '';
                if (query === '' || name.includes(query)) {
                    item.classList.remove('component-hidden');
                } else {
                    item.classList.add('component-hidden');
                }
            });

            // Show/hide categories based on visible items
            document.querySelectorAll('.storybook__category').forEach(cat => {
                const visibleItems = cat.querySelectorAll('.storybook__component-item:not(.component-hidden)');
                if (visibleItems.length === 0 && query !== '') {
                    cat.classList.add('component-hidden');
                } else {
                    cat.classList.remove('component-hidden');
                }
            });
        }
    </script>
</body>
</html>
