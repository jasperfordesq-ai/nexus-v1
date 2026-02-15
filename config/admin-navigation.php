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
        'url' => '/admin-legacy',
        'single' => true,
    ],

    // === CORE ===
    'core' => [
        'label' => 'Core',
        'sections' => [
            'users' => [
                'label' => 'Users',
                'icon' => 'fa-users',
                'url' => '/admin-legacy/users',
                'badge' => 'pending_users', // Badge count key
                'children' => [
                    ['label' => 'All Users', 'url' => '/admin-legacy/users', 'icon' => 'fa-users'],
                    ['label' => 'Pending Approvals', 'url' => '/admin-legacy/users?filter=pending', 'icon' => 'fa-user-clock', 'badge' => 'pending_users'],
                ],
            ],
            'listings' => [
                'label' => 'Listings',
                'icon' => 'fa-rectangle-list',
                'url' => '/admin-legacy/listings',
                'badge' => 'pending_listings', // Badge count key
                'children' => [
                    ['label' => 'All Listings', 'url' => '/admin-legacy/listings', 'icon' => 'fa-list'],
                    ['label' => 'Pending Review', 'url' => '/admin-legacy/listings?status=pending', 'icon' => 'fa-clock', 'badge' => 'pending_listings'],
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
                'url' => '/admin-legacy/groups',
                'children' => [
                    ['label' => 'All Groups', 'url' => '/admin-legacy/groups', 'icon' => 'fa-users-rectangle'],
                    ['label' => 'Group Types', 'url' => '/admin-legacy/group-types', 'icon' => 'fa-layer-group'],
                    ['label' => 'Ranking', 'url' => '/admin-legacy/group-ranking', 'icon' => 'fa-star'],
                    ['label' => 'Analytics', 'url' => '/admin-legacy/groups/analytics', 'icon' => 'fa-chart-bar'],
                ],
            ],
            'organizations' => [
                'label' => 'Organizations',
                'icon' => 'fa-building',
                'url' => '/admin-legacy/volunteering/organizations',
                'badge' => 'pending_orgs', // Badge count key
                'children' => [
                    ['label' => 'All Organizations', 'url' => '/admin-legacy/volunteering/organizations', 'icon' => 'fa-building'],
                    ['label' => 'Pending Approvals', 'url' => '/admin-legacy/volunteering/approvals', 'icon' => 'fa-hands-helping', 'badge' => 'pending_orgs'],
                ],
            ],
            'smart-systems' => [
                'label' => 'Smart Systems',
                'icon' => 'fa-brain',
                'url' => '/admin-legacy/smart-match-users',
                'children' => [
                    ['label' => 'Smart Matching', 'url' => '/admin-legacy/smart-match-users', 'icon' => 'fa-users-between-lines'],
                    ['label' => 'Match Approvals', 'url' => '/admin-legacy/match-approvals', 'icon' => 'fa-clipboard-check'],
                    ['label' => 'Recommendations', 'url' => '/admin-legacy/groups/recommendations', 'icon' => 'fa-sparkles'],
                    ['label' => 'Monitoring', 'url' => '/admin-legacy/smart-match-monitoring', 'icon' => 'fa-chart-line'],
                    ['label' => 'Geocoding', 'url' => '/admin-legacy/geocode-groups', 'icon' => 'fa-map-location-dot'],
                    ['label' => 'Locations', 'url' => '/admin-legacy/group-locations', 'icon' => 'fa-location-dot'],
                ],
            ],
            'broker-controls' => [
                'label' => 'Broker Controls',
                'icon' => 'fa-shield-halved',
                'url' => '/admin-legacy/broker-controls',
                'badge' => 'pending_exchanges',
                'children' => [
                    ['label' => 'Dashboard', 'url' => '/admin-legacy/broker-controls', 'icon' => 'fa-gauge-high'],
                    ['label' => 'Configuration', 'url' => '/admin-legacy/broker-controls/configuration', 'icon' => 'fa-sliders'],
                    ['label' => 'Exchange Requests', 'url' => '/admin-legacy/broker-controls/exchanges', 'icon' => 'fa-handshake', 'badge' => 'pending_exchanges'],
                    ['label' => 'Risk Tags', 'url' => '/admin-legacy/broker-controls/risk-tags', 'icon' => 'fa-tags'],
                    ['label' => 'Message Review', 'url' => '/admin-legacy/broker-controls/messages', 'icon' => 'fa-envelope-open-text', 'badge' => 'unreviewed_messages'],
                    ['label' => 'User Monitoring', 'url' => '/admin-legacy/broker-controls/monitoring', 'icon' => 'fa-user-shield'],
                    ['label' => 'Statistics', 'url' => '/admin-legacy/broker-controls/stats', 'icon' => 'fa-chart-pie'],
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
                'url' => '/admin-legacy/blog',
                'children' => [
                    ['label' => 'Blog Posts', 'url' => '/admin-legacy/blog', 'icon' => 'fa-blog'],
                    ['label' => 'Pages', 'url' => '/admin-legacy/pages', 'icon' => 'fa-file-lines'],
                    ['label' => 'Menus', 'url' => '/admin-legacy/menus', 'icon' => 'fa-bars'],
                    ['label' => 'Categories', 'url' => '/admin-legacy/categories', 'icon' => 'fa-folder'],
                    ['label' => 'Attributes', 'url' => '/admin-legacy/attributes', 'icon' => 'fa-tags'],
                ],
            ],
            'engagement' => [
                'label' => 'Engagement',
                'icon' => 'fa-trophy',
                'url' => '/admin-legacy/gamification',
                'children' => [
                    ['label' => 'Gamification Hub', 'url' => '/admin-legacy/gamification', 'icon' => 'fa-gamepad'],
                    ['label' => 'Campaigns', 'url' => '/admin-legacy/gamification/campaigns', 'icon' => 'fa-bullhorn'],
                    ['label' => 'Custom Badges', 'url' => '/admin-legacy/custom-badges', 'icon' => 'fa-medal'],
                    ['label' => 'Analytics', 'url' => '/admin-legacy/gamification/analytics', 'icon' => 'fa-chart-bar'],
                ],
            ],
            'marketing' => [
                'label' => 'Marketing',
                'icon' => 'fa-megaphone',
                'url' => '/admin-legacy/newsletters',
                'children' => [
                    ['label' => 'Newsletters', 'url' => '/admin-legacy/newsletters', 'icon' => 'fa-envelopes-bulk'],
                    ['label' => 'Subscribers', 'url' => '/admin-legacy/newsletters/subscribers', 'icon' => 'fa-user-plus'],
                    ['label' => 'Segments', 'url' => '/admin-legacy/newsletters/segments', 'icon' => 'fa-layer-group'],
                    ['label' => 'Templates', 'url' => '/admin-legacy/newsletters/templates', 'icon' => 'fa-palette'],
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
                'url' => '/admin-legacy/ai-settings',
                'children' => [
                    ['label' => 'AI Settings', 'url' => '/admin-legacy/ai-settings', 'icon' => 'fa-microchip'],
                    ['label' => 'Smart Matching', 'url' => '/admin-legacy/smart-matching', 'icon' => 'fa-wand-magic-sparkles'],
                    ['label' => 'Feed Algorithm', 'url' => '/admin-legacy/feed-algorithm', 'icon' => 'fa-rss'],
                    ['label' => 'Algorithm Settings', 'url' => '/admin-legacy/algorithm-settings', 'icon' => 'fa-scale-balanced'],
                ],
            ],
            'seo' => [
                'label' => 'SEO',
                'icon' => 'fa-chart-line',
                'url' => '/admin-legacy/seo',
                'children' => [
                    ['label' => 'Overview', 'url' => '/admin-legacy/seo', 'icon' => 'fa-chart-line'],
                    ['label' => 'Audit', 'url' => '/admin-legacy/seo/audit', 'icon' => 'fa-clipboard-check'],
                    ['label' => 'Bulk Edit', 'url' => '/admin-legacy/seo/bulk/listing', 'icon' => 'fa-pen-to-square'],
                    ['label' => 'Redirects', 'url' => '/admin-legacy/seo/redirects', 'icon' => 'fa-arrow-right-arrow-left'],
                    ['label' => '404 Errors', 'url' => '/admin-legacy/404-errors', 'icon' => 'fa-exclamation-triangle', 'badge' => '404_errors', 'badgeType' => 'info'],
                ],
            ],
            'financial' => [
                'label' => 'Financial',
                'icon' => 'fa-coins',
                'url' => '/admin-legacy/timebanking',
                'badge' => 'fraud_alerts', // Badge count key
                'children' => [
                    ['label' => 'Timebanking', 'url' => '/admin-legacy/timebanking', 'icon' => 'fa-clock-rotate-left'],
                    ['label' => 'User Report', 'url' => '/admin-legacy/timebanking/user-report', 'icon' => 'fa-user-clock'],
                    ['label' => 'Fraud Alerts', 'url' => '/admin-legacy/timebanking/alerts', 'icon' => 'fa-triangle-exclamation', 'badge' => 'fraud_alerts', 'badgeType' => 'danger'],
                    ['label' => 'Org Wallets', 'url' => '/admin-legacy/timebanking/org-wallets', 'icon' => 'fa-wallet'],
                    ['label' => 'Create Org', 'url' => '/admin-legacy/timebanking/create-org', 'icon' => 'fa-building-circle-plus'],
                    ['label' => 'Plans & Pricing', 'url' => '/admin-legacy/plans', 'icon' => 'fa-credit-card'],
                    ['label' => 'Subscriptions', 'url' => '/admin-legacy/plans/subscriptions', 'icon' => 'fa-users'],
                ],
            ],
            'enterprise' => [
                'label' => 'Enterprise',
                'icon' => 'fa-building-shield',
                'url' => '/admin-legacy/enterprise',
                'badge' => 'gdpr_requests', // Badge count key
                'children' => [
                    ['label' => 'Dashboard', 'url' => '/admin-legacy/enterprise', 'icon' => 'fa-building-shield'],
                    ['label' => 'Roles & Permissions', 'url' => '/admin-legacy/enterprise/roles', 'icon' => 'fa-user-tag'],
                    ['label' => 'Permission Browser', 'url' => '/admin-legacy/enterprise/permissions', 'icon' => 'fa-key'],
                    ['label' => 'GDPR Dashboard', 'url' => '/admin-legacy/enterprise/gdpr', 'icon' => 'fa-user-shield'],
                    ['label' => 'Data Requests', 'url' => '/admin-legacy/enterprise/gdpr/requests', 'icon' => 'fa-file-contract', 'badge' => 'gdpr_requests'],
                    ['label' => 'Monitoring', 'url' => '/admin-legacy/enterprise/monitoring', 'icon' => 'fa-heart-pulse'],
                    ['label' => 'System Config', 'url' => '/admin-legacy/enterprise/config', 'icon' => 'fa-gears'],
                ],
            ],
            'system' => [
                'label' => 'System',
                'icon' => 'fa-gear',
                'url' => '/admin-legacy/settings',
                'children' => [
                    ['label' => 'Settings', 'url' => '/admin-legacy/settings', 'icon' => 'fa-sliders'],
                    ['label' => 'Seed Generator', 'url' => '/admin-legacy/seed-generator', 'icon' => 'fa-seedling'],
                    ['label' => 'Blog Restore', 'url' => '/admin-legacy/blog-restore', 'icon' => 'fa-rotate-left'],
                    ['label' => 'Cron Jobs', 'url' => '/admin-legacy/cron-jobs', 'icon' => 'fa-clock'],
                    ['label' => 'Activity Log', 'url' => '/admin-legacy/activity-log', 'icon' => 'fa-list-ul'],
                    ['label' => 'API Test Runner', 'url' => '/admin-legacy/tests', 'icon' => 'fa-flask'],
                    ['label' => 'Native App', 'url' => '/admin-legacy/native-app', 'icon' => 'fa-mobile-screen'],
                    ['label' => 'WebP Converter', 'url' => '/admin-legacy/webp-converter', 'icon' => 'fa-image'],
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
                'url' => '/admin-legacy/federation',
                'condition' => 'federation', // Only show if federation is enabled
                'children' => [
                    ['label' => 'Settings', 'url' => '/admin-legacy/federation', 'icon' => 'fa-sliders'],
                    ['label' => 'Partnerships', 'url' => '/admin-legacy/federation/partnerships', 'icon' => 'fa-handshake'],
                    ['label' => 'External Partners', 'url' => '/admin-legacy/federation/external-partners', 'icon' => 'fa-server'],
                    ['label' => 'Discover', 'url' => '/admin-legacy/federation/directory', 'icon' => 'fa-compass'],
                    ['label' => 'My Listing', 'url' => '/admin-legacy/federation/directory/profile', 'icon' => 'fa-user-edit'],
                    ['label' => 'Analytics', 'url' => '/admin-legacy/federation/analytics', 'icon' => 'fa-chart-line'],
                    ['label' => 'API Keys', 'url' => '/admin-legacy/federation/api-keys', 'icon' => 'fa-key'],
                    ['label' => 'Data Management', 'url' => '/admin-legacy/federation/data', 'icon' => 'fa-database'],
                ],
            ],
            'deliverability' => [
                'label' => 'Deliverability',
                'icon' => 'fa-tasks-alt',
                'url' => '/admin-legacy/deliverability',
                'children' => [
                    ['label' => 'Dashboard', 'url' => '/admin-legacy/deliverability', 'icon' => 'fa-gauge-high'],
                    ['label' => 'All Deliverables', 'url' => '/admin-legacy/deliverability/list', 'icon' => 'fa-list'],
                    ['label' => 'Create New', 'url' => '/admin-legacy/deliverability/create', 'icon' => 'fa-plus'],
                    ['label' => 'Analytics', 'url' => '/admin-legacy/deliverability/analytics', 'icon' => 'fa-chart-line'],
                ],
            ],
        ],
    ],
];
