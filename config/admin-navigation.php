<?php
/**
 * Admin Navigation Configuration
 * Single source of truth for admin sidebar navigation
 *
 * Structure:
 * - Groups contain sections
 * - Sections contain items
 * - Items can have sub-items (children)
 */

return [
    // Dashboard - single link, no group
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'fa-gauge-high',
        'url' => '/admin',
        'single' => true,
    ],

    // === CORE ===
    'core' => [
        'label' => 'Core',
        'sections' => [
            'users' => [
                'label' => 'Users',
                'icon' => 'fa-users',
                'url' => '/admin/users',
                'badge' => 'pending_users', // Badge count key
                'children' => [
                    ['label' => 'All Users', 'url' => '/admin/users', 'icon' => 'fa-users'],
                    ['label' => 'Pending Approvals', 'url' => '/admin/users?filter=pending', 'icon' => 'fa-user-clock', 'badge' => 'pending_users'],
                ],
            ],
            'listings' => [
                'label' => 'Listings',
                'icon' => 'fa-rectangle-list',
                'url' => '/admin/listings',
                'badge' => 'pending_listings', // Badge count key
                'children' => [
                    ['label' => 'All Listings', 'url' => '/admin/listings', 'icon' => 'fa-list'],
                    ['label' => 'Pending Review', 'url' => '/admin/listings?status=pending', 'icon' => 'fa-clock', 'badge' => 'pending_listings'],
                ],
            ],
        ],
    ],

    // === COMMUNITY ===
    'community' => [
        'label' => 'Community',
        'sections' => [
            'groups' => [
                'label' => 'Groups',
                'icon' => 'fa-users-rectangle',
                'url' => '/admin/groups',
                'children' => [
                    ['label' => 'All Groups', 'url' => '/admin/groups', 'icon' => 'fa-users-rectangle'],
                    ['label' => 'Group Types', 'url' => '/admin/group-types', 'icon' => 'fa-layer-group'],
                    ['label' => 'Ranking', 'url' => '/admin/group-ranking', 'icon' => 'fa-star'],
                    ['label' => 'Analytics', 'url' => '/admin/groups/analytics', 'icon' => 'fa-chart-bar'],
                ],
            ],
            'organizations' => [
                'label' => 'Organizations',
                'icon' => 'fa-building',
                'url' => '/admin/volunteering/organizations',
                'badge' => 'pending_orgs', // Badge count key
                'children' => [
                    ['label' => 'All Organizations', 'url' => '/admin/volunteering/organizations', 'icon' => 'fa-building'],
                    ['label' => 'Pending Approvals', 'url' => '/admin/volunteering/approvals', 'icon' => 'fa-hands-helping', 'badge' => 'pending_orgs'],
                ],
            ],
            'smart-systems' => [
                'label' => 'Smart Systems',
                'icon' => 'fa-brain',
                'url' => '/admin/smart-match-users',
                'children' => [
                    ['label' => 'Smart Matching', 'url' => '/admin/smart-match-users', 'icon' => 'fa-users-between-lines'],
                    ['label' => 'Recommendations', 'url' => '/admin/groups/recommendations', 'icon' => 'fa-sparkles'],
                    ['label' => 'Monitoring', 'url' => '/admin/smart-match-monitoring', 'icon' => 'fa-chart-line'],
                    ['label' => 'Geocoding', 'url' => '/admin/geocode-groups', 'icon' => 'fa-map-location-dot'],
                    ['label' => 'Locations', 'url' => '/admin/group-locations', 'icon' => 'fa-location-dot'],
                ],
            ],
        ],
    ],

    // === CONTENT ===
    'content' => [
        'label' => 'Content',
        'sections' => [
            'content' => [
                'label' => 'Content',
                'icon' => 'fa-newspaper',
                'url' => '/admin/blog',
                'children' => [
                    ['label' => 'Blog Posts', 'url' => '/admin/blog', 'icon' => 'fa-blog'],
                    ['label' => 'Pages', 'url' => '/admin/pages', 'icon' => 'fa-file-lines'],
                    ['label' => 'Menus', 'url' => '/admin/menus', 'icon' => 'fa-bars'],
                    ['label' => 'Categories', 'url' => '/admin/categories', 'icon' => 'fa-folder'],
                    ['label' => 'Attributes', 'url' => '/admin/attributes', 'icon' => 'fa-tags'],
                ],
            ],
            'engagement' => [
                'label' => 'Engagement',
                'icon' => 'fa-trophy',
                'url' => '/admin/gamification',
                'children' => [
                    ['label' => 'Gamification Hub', 'url' => '/admin/gamification', 'icon' => 'fa-gamepad'],
                    ['label' => 'Campaigns', 'url' => '/admin/gamification/campaigns', 'icon' => 'fa-bullhorn'],
                    ['label' => 'Custom Badges', 'url' => '/admin/custom-badges', 'icon' => 'fa-medal'],
                    ['label' => 'Analytics', 'url' => '/admin/gamification/analytics', 'icon' => 'fa-chart-bar'],
                ],
            ],
            'marketing' => [
                'label' => 'Marketing',
                'icon' => 'fa-megaphone',
                'url' => '/admin/newsletters',
                'children' => [
                    ['label' => 'Newsletters', 'url' => '/admin/newsletters', 'icon' => 'fa-envelopes-bulk'],
                    ['label' => 'Subscribers', 'url' => '/admin/newsletters/subscribers', 'icon' => 'fa-user-plus'],
                    ['label' => 'Segments', 'url' => '/admin/newsletters/segments', 'icon' => 'fa-layer-group'],
                    ['label' => 'Templates', 'url' => '/admin/newsletters/templates', 'icon' => 'fa-palette'],
                ],
            ],
        ],
    ],

    // === PLATFORM ===
    'platform' => [
        'label' => 'Platform',
        'sections' => [
            'ai' => [
                'label' => 'AI & Automation',
                'icon' => 'fa-microchip',
                'url' => '/admin/ai-settings',
                'children' => [
                    ['label' => 'AI Settings', 'url' => '/admin/ai-settings', 'icon' => 'fa-microchip'],
                    ['label' => 'Smart Matching', 'url' => '/admin/smart-matching', 'icon' => 'fa-wand-magic-sparkles'],
                    ['label' => 'Feed Algorithm', 'url' => '/admin/feed-algorithm', 'icon' => 'fa-rss'],
                    ['label' => 'Algorithm Settings', 'url' => '/admin/algorithm-settings', 'icon' => 'fa-scale-balanced'],
                ],
            ],
            'seo' => [
                'label' => 'SEO',
                'icon' => 'fa-chart-line',
                'url' => '/admin/seo',
                'children' => [
                    ['label' => 'Overview', 'url' => '/admin/seo', 'icon' => 'fa-chart-line'],
                    ['label' => 'Audit', 'url' => '/admin/seo/audit', 'icon' => 'fa-clipboard-check'],
                    ['label' => 'Bulk Edit', 'url' => '/admin/seo/bulk/listing', 'icon' => 'fa-pen-to-square'],
                    ['label' => 'Redirects', 'url' => '/admin/seo/redirects', 'icon' => 'fa-arrow-right-arrow-left'],
                    ['label' => '404 Errors', 'url' => '/admin/404-errors', 'icon' => 'fa-exclamation-triangle', 'badge' => '404_errors', 'badgeType' => 'info'],
                ],
            ],
            'financial' => [
                'label' => 'Financial',
                'icon' => 'fa-coins',
                'url' => '/admin/timebanking',
                'badge' => 'fraud_alerts', // Badge count key
                'children' => [
                    ['label' => 'Timebanking', 'url' => '/admin/timebanking', 'icon' => 'fa-clock-rotate-left'],
                    ['label' => 'User Report', 'url' => '/admin/timebanking/user-report', 'icon' => 'fa-user-clock'],
                    ['label' => 'Fraud Alerts', 'url' => '/admin/timebanking/alerts', 'icon' => 'fa-triangle-exclamation', 'badge' => 'fraud_alerts', 'badgeType' => 'danger'],
                    ['label' => 'Org Wallets', 'url' => '/admin/timebanking/org-wallets', 'icon' => 'fa-wallet'],
                    ['label' => 'Create Org', 'url' => '/admin/timebanking/create-org', 'icon' => 'fa-building-circle-plus'],
                    ['label' => 'Plans & Pricing', 'url' => '/admin/plans', 'icon' => 'fa-credit-card'],
                    ['label' => 'Subscriptions', 'url' => '/admin/plans/subscriptions', 'icon' => 'fa-users'],
                ],
            ],
            'enterprise' => [
                'label' => 'Enterprise',
                'icon' => 'fa-building-shield',
                'url' => '/admin/enterprise',
                'badge' => 'gdpr_requests', // Badge count key
                'children' => [
                    ['label' => 'Dashboard', 'url' => '/admin/enterprise', 'icon' => 'fa-building-shield'],
                    ['label' => 'Roles & Permissions', 'url' => '/admin/enterprise/roles', 'icon' => 'fa-user-tag'],
                    ['label' => 'Permission Browser', 'url' => '/admin/enterprise/permissions', 'icon' => 'fa-key'],
                    ['label' => 'GDPR Dashboard', 'url' => '/admin/enterprise/gdpr', 'icon' => 'fa-user-shield'],
                    ['label' => 'Data Requests', 'url' => '/admin/enterprise/gdpr/requests', 'icon' => 'fa-file-contract', 'badge' => 'gdpr_requests'],
                    ['label' => 'Monitoring', 'url' => '/admin/enterprise/monitoring', 'icon' => 'fa-heart-pulse'],
                    ['label' => 'System Config', 'url' => '/admin/enterprise/config', 'icon' => 'fa-gears'],
                ],
            ],
            'system' => [
                'label' => 'System',
                'icon' => 'fa-gear',
                'url' => '/admin/settings',
                'children' => [
                    ['label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'fa-sliders'],
                    ['label' => 'Seed Generator', 'url' => '/admin/seed-generator', 'icon' => 'fa-seedling'],
                    ['label' => 'Blog Restore', 'url' => '/admin/blog-restore', 'icon' => 'fa-rotate-left'],
                    ['label' => 'Cron Jobs', 'url' => '/admin/cron-jobs', 'icon' => 'fa-clock'],
                    ['label' => 'Activity Log', 'url' => '/admin/activity-log', 'icon' => 'fa-list-ul'],
                    ['label' => 'API Test Runner', 'url' => '/admin/tests', 'icon' => 'fa-flask'],
                    ['label' => 'Native App', 'url' => '/admin/native-app', 'icon' => 'fa-mobile-screen'],
                    ['label' => 'WebP Converter', 'url' => '/admin/webp-converter', 'icon' => 'fa-image'],
                ],
            ],
        ],
    ],

    // === EXTENSIONS ===
    'extensions' => [
        'label' => 'Extensions',
        'sections' => [
            'federation' => [
                'label' => 'Federation',
                'icon' => 'fa-globe',
                'url' => '/admin/federation',
                'condition' => 'federation', // Only show if federation is enabled
                'children' => [
                    ['label' => 'Settings', 'url' => '/admin/federation', 'icon' => 'fa-sliders'],
                    ['label' => 'Partnerships', 'url' => '/admin/federation/partnerships', 'icon' => 'fa-handshake'],
                    ['label' => 'Discover', 'url' => '/admin/federation/directory', 'icon' => 'fa-compass'],
                    ['label' => 'My Listing', 'url' => '/admin/federation/directory/profile', 'icon' => 'fa-user-edit'],
                    ['label' => 'Analytics', 'url' => '/admin/federation/analytics', 'icon' => 'fa-chart-line'],
                    ['label' => 'API Keys', 'url' => '/admin/federation/api-keys', 'icon' => 'fa-key'],
                    ['label' => 'Data Management', 'url' => '/admin/federation/data', 'icon' => 'fa-database'],
                ],
            ],
            'deliverability' => [
                'label' => 'Deliverability',
                'icon' => 'fa-tasks-alt',
                'url' => '/admin/deliverability',
                'children' => [
                    ['label' => 'Dashboard', 'url' => '/admin/deliverability', 'icon' => 'fa-gauge-high'],
                    ['label' => 'All Deliverables', 'url' => '/admin/deliverability/list', 'icon' => 'fa-list'],
                    ['label' => 'Create New', 'url' => '/admin/deliverability/create', 'icon' => 'fa-plus'],
                    ['label' => 'Analytics', 'url' => '/admin/deliverability/analytics', 'icon' => 'fa-chart-line'],
                ],
            ],
        ],
    ],
];
