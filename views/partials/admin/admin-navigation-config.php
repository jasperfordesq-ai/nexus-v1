<?php
/**
 * Admin Navigation Configuration
 * Shared navigation structure used by both Modern and CivicOne themes
 *
 * @package Nexus\Admin
 */

use Nexus\Core\TenantContext;

/**
 * Get the admin navigation modules configuration
 *
 * @return array Admin navigation modules
 */
function getAdminNavigationModules(): array
{
    return [
        'dashboard' => [
            'label' => 'Dashboard',
            'icon' => 'fa-gauge-high',
            'url' => '/admin',
            'single' => true,
        ],

        // === CORE OPERATIONS ===
        'users' => [
            'label' => 'Users',
            'icon' => 'fa-users',
            'items' => [
                ['label' => 'All Users', 'url' => '/admin/users', 'icon' => 'fa-users', 'desc' => 'Manage all platform users'],
                ['label' => 'Pending Approvals', 'url' => '/admin/users?filter=pending', 'icon' => 'fa-user-clock', 'desc' => 'Review pending user accounts'],
            ],
        ],

        'listings' => [
            'label' => 'Listings',
            'icon' => 'fa-rectangle-list',
            'items' => [
                ['label' => 'All Listings', 'url' => '/admin/listings', 'icon' => 'fa-list', 'desc' => 'View all listings'],
                ['label' => 'Pending Review', 'url' => '/admin/listings?status=pending', 'icon' => 'fa-clock', 'desc' => 'Approve new listings'],
            ],
        ],

        // === COMMUNITY (MEGA MENU) ===
        'community' => [
            'label' => 'Community',
            'icon' => 'fa-people-group',
            'mega' => true,
            'columns' => [
                [
                    'title' => 'Groups',
                    'items' => [
                        ['label' => 'All Groups', 'url' => '/admin/groups', 'icon' => 'fa-users-rectangle'],
                        ['label' => 'Group Types', 'url' => '/admin/group-types', 'icon' => 'fa-layer-group'],
                        ['label' => 'Group Ranking', 'url' => '/admin/group-ranking', 'icon' => 'fa-star'],
                        ['label' => 'Group Analytics', 'url' => '/admin/groups/analytics', 'icon' => 'fa-chart-bar'],
                    ],
                ],
                [
                    'title' => 'Organizations',
                    'items' => [
                        ['label' => 'All Organizations', 'url' => '/admin/volunteering/organizations', 'icon' => 'fa-building'],
                        ['label' => 'Pending Approvals', 'url' => '/admin/volunteering/approvals', 'icon' => 'fa-hands-helping'],
                    ],
                ],
                [
                    'title' => 'Smart Systems',
                    'items' => [
                        ['label' => 'Smart Matching', 'url' => '/admin/smart-match-users', 'icon' => 'fa-users-between-lines'],
                        ['label' => 'Match Approvals', 'url' => '/admin/match-approvals', 'icon' => 'fa-clipboard-check', 'badge' => 'NEW'],
                        ['label' => 'Recommendations', 'url' => '/admin/groups/recommendations', 'icon' => 'fa-sparkles'],
                        ['label' => 'Match Monitoring', 'url' => '/admin/smart-match-monitoring', 'icon' => 'fa-chart-line'],
                        ['label' => 'Geocoding', 'url' => '/admin/geocode-groups', 'icon' => 'fa-map-location-dot'],
                        ['label' => 'Locations', 'url' => '/admin/group-locations', 'icon' => 'fa-location-dot'],
                    ],
                ],
            ],
        ],

        // === CONTENT ===
        'content' => [
            'label' => 'Content',
            'icon' => 'fa-newspaper',
            'items' => [
                ['label' => 'Blog Posts', 'url' => '/admin/blog', 'icon' => 'fa-blog'],
                ['label' => 'Pages', 'url' => '/admin/pages', 'icon' => 'fa-file-lines'],
                ['label' => 'Menus', 'url' => '/admin/menus', 'icon' => 'fa-bars'],
                ['label' => 'Categories', 'url' => '/admin/categories', 'icon' => 'fa-folder'],
                ['label' => 'Attributes', 'url' => '/admin/attributes', 'icon' => 'fa-tags'],
            ],
        ],

        // === ENGAGEMENT ===
        'engagement' => [
            'label' => 'Engagement',
            'icon' => 'fa-trophy',
            'items' => [
                ['label' => 'Gamification Hub', 'url' => '/admin/gamification', 'icon' => 'fa-gamepad'],
                ['label' => 'Campaigns', 'url' => '/admin/gamification/campaigns', 'icon' => 'fa-bullhorn'],
                ['label' => 'Custom Badges', 'url' => '/admin/custom-badges', 'icon' => 'fa-medal'],
                ['label' => 'Analytics', 'url' => '/admin/gamification/analytics', 'icon' => 'fa-chart-bar'],
            ],
        ],

        // === MARKETING & COMMUNICATION ===
        'marketing' => [
            'label' => 'Marketing',
            'icon' => 'fa-megaphone',
            'items' => [
                ['label' => 'Newsletters', 'url' => '/admin/newsletters', 'icon' => 'fa-envelopes-bulk'],
                ['label' => 'Subscribers', 'url' => '/admin/newsletters/subscribers', 'icon' => 'fa-user-plus'],
                ['label' => 'Segments', 'url' => '/admin/newsletters/segments', 'icon' => 'fa-layer-group'],
                ['label' => 'Templates', 'url' => '/admin/newsletters/templates', 'icon' => 'fa-palette'],
            ],
        ],

        // === ADVANCED (MEGA MENU) ===
        'advanced' => [
            'label' => 'Advanced',
            'icon' => 'fa-wand-magic-sparkles',
            'mega' => true,
            'columns' => [
                [
                    'title' => 'AI & Automation',
                    'items' => [
                        ['label' => 'AI Settings', 'url' => '/admin/ai-settings', 'icon' => 'fa-microchip'],
                        ['label' => 'Smart Matching', 'url' => '/admin/smart-matching', 'icon' => 'fa-wand-magic-sparkles'],
                        ['label' => 'Feed Algorithm', 'url' => '/admin/feed-algorithm', 'icon' => 'fa-rss'],
                        ['label' => 'Algorithm Settings', 'url' => '/admin/algorithm-settings', 'icon' => 'fa-scale-balanced'],
                    ],
                ],
                [
                    'title' => 'SEO & Optimization',
                    'items' => [
                        ['label' => 'SEO Overview', 'url' => '/admin/seo', 'icon' => 'fa-chart-line'],
                        ['label' => 'SEO Audit', 'url' => '/admin/seo/audit', 'icon' => 'fa-clipboard-check'],
                        ['label' => 'Bulk Edit', 'url' => '/admin/seo/bulk/listing', 'icon' => 'fa-pen-to-square'],
                        ['label' => 'Redirects', 'url' => '/admin/seo/redirects', 'icon' => 'fa-arrow-right-arrow-left'],
                        ['label' => '404 Error Tracking', 'url' => '/admin/404-errors', 'icon' => 'fa-exclamation-triangle', 'badge' => 'NEW'],
                    ],
                ],
            ],
        ],

        // === FINANCIAL ===
        'financial' => [
            'label' => 'Financial',
            'icon' => 'fa-coins',
            'items' => [
                ['label' => 'Timebanking', 'url' => '/admin/timebanking', 'icon' => 'fa-clock-rotate-left'],
                ['label' => 'User Report', 'url' => '/admin/timebanking/user-report', 'icon' => 'fa-user-clock'],
                ['label' => 'Fraud Alerts', 'url' => '/admin/timebanking/alerts', 'icon' => 'fa-triangle-exclamation'],
                ['label' => 'Org Wallets', 'url' => '/admin/timebanking/org-wallets', 'icon' => 'fa-wallet'],
                ['label' => 'Create Org', 'url' => '/admin/timebanking/create-org', 'icon' => 'fa-building-circle-plus'],
                ['label' => 'Plans & Pricing', 'url' => '/admin/plans', 'icon' => 'fa-credit-card'],
                ['label' => 'Subscriptions', 'url' => '/admin/plans/subscriptions', 'icon' => 'fa-users'],
            ],
        ],

        // === ENTERPRISE (MEGA MENU) ===
        'enterprise' => [
            'label' => 'Enterprise',
            'icon' => 'fa-building-shield',
            'mega' => true,
            'columns' => [
                [
                    'title' => 'Overview',
                    'items' => [
                        ['label' => 'Enterprise Dashboard', 'url' => '/admin/enterprise', 'icon' => 'fa-building-shield'],
                        ['label' => 'Roles & Permissions', 'url' => '/admin/enterprise/roles', 'icon' => 'fa-user-tag'],
                        ['label' => 'Permission Browser', 'url' => '/admin/enterprise/permissions', 'icon' => 'fa-key'],
                        ['label' => 'Permissions Audit', 'url' => '/admin/enterprise/audit/permissions', 'icon' => 'fa-clipboard-list'],
                    ],
                ],
                [
                    'title' => 'GDPR Compliance',
                    'items' => [
                        ['label' => 'GDPR Dashboard', 'url' => '/admin/enterprise/gdpr', 'icon' => 'fa-user-shield'],
                        ['label' => 'Data Requests', 'url' => '/admin/enterprise/gdpr/requests', 'icon' => 'fa-file-contract'],
                        ['label' => 'Consent Records', 'url' => '/admin/enterprise/gdpr/consents', 'icon' => 'fa-check-double'],
                        ['label' => 'Data Breaches', 'url' => '/admin/enterprise/gdpr/breaches', 'icon' => 'fa-triangle-exclamation'],
                        ['label' => 'GDPR Audit Log', 'url' => '/admin/enterprise/gdpr/audit', 'icon' => 'fa-scroll'],
                    ],
                ],
                [
                    'title' => 'Legal Documents',
                    'items' => [
                        ['label' => 'All Documents', 'url' => '/admin/legal-documents', 'icon' => 'fa-file-contract'],
                        ['label' => 'Compliance Dashboard', 'url' => '/admin/legal-documents/compliance', 'icon' => 'fa-clipboard-check'],
                    ],
                ],
                [
                    'title' => 'System Health',
                    'items' => [
                        ['label' => 'Monitoring Dashboard', 'url' => '/admin/enterprise/monitoring', 'icon' => 'fa-heart-pulse'],
                        ['label' => 'Health Check', 'url' => '/admin/enterprise/monitoring/health', 'icon' => 'fa-stethoscope'],
                        ['label' => 'Requirements', 'url' => '/admin/enterprise/monitoring/requirements', 'icon' => 'fa-list-check'],
                        ['label' => 'Error Logs', 'url' => '/admin/enterprise/monitoring/logs', 'icon' => 'fa-file-lines'],
                    ],
                ],
                [
                    'title' => 'Configuration',
                    'items' => [
                        ['label' => 'System Config', 'url' => '/admin/enterprise/config', 'icon' => 'fa-gears'],
                        ['label' => 'Feature Flags', 'url' => '/admin/enterprise/config#features', 'icon' => 'fa-toggle-on'],
                        ['label' => 'Secrets Vault', 'url' => '/admin/enterprise/config/secrets', 'icon' => 'fa-vault'],
                    ],
                ],
            ],
        ],

        // === PARTNER TIMEBANKS (Federation) ===
        'federation' => [
            'label' => 'Partner Timebanks',
            'icon' => 'fa-globe',
            'items' => [
                ['label' => 'Settings', 'url' => '/admin/federation', 'icon' => 'fa-sliders'],
                ['label' => 'Partnerships', 'url' => '/admin/federation/partnerships', 'icon' => 'fa-handshake'],
                ['label' => 'Discover', 'url' => '/admin/federation/directory', 'icon' => 'fa-compass'],
                ['label' => 'My Listing', 'url' => '/admin/federation/directory/profile', 'icon' => 'fa-user-edit'],
                ['label' => 'Analytics', 'url' => '/admin/federation/analytics', 'icon' => 'fa-chart-line'],
                ['label' => 'API Keys', 'url' => '/admin/federation/api-keys', 'icon' => 'fa-key'],
                ['label' => 'Data Management', 'url' => '/admin/federation/data', 'icon' => 'fa-database'],
            ],
            'condition' => 'federation',
        ],

        // === SYSTEM ===
        'system' => [
            'label' => 'System',
            'icon' => 'fa-gear',
            'items' => [
                ['label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'fa-sliders'],
                ['label' => 'Seed Generator', 'url' => '/admin/seed-generator', 'icon' => 'fa-seedling', 'desc' => 'Generate database seeding scripts'],
                ['label' => 'Blog Restore', 'url' => '/admin/blog-restore', 'icon' => 'fa-rotate-left', 'desc' => 'Import/Export blog posts', 'badge' => 'NEW'],
                ['label' => 'Cron Jobs', 'url' => '/admin/cron-jobs', 'icon' => 'fa-clock'],
                ['label' => 'Activity Log', 'url' => '/admin/activity-log', 'icon' => 'fa-list-ul'],
                ['label' => 'API Test Runner', 'url' => '/admin/tests', 'icon' => 'fa-flask', 'desc' => 'Run automated API tests'],
                ['label' => 'Native App', 'url' => '/admin/native-app', 'icon' => 'fa-mobile-screen'],
                ['label' => 'WebP Converter', 'url' => '/admin/webp-converter', 'icon' => 'fa-image', 'desc' => 'Convert images to WebP format'],
            ],
        ],

        // === DELIVERABILITY TRACKING ===
        'deliverability' => [
            'label' => 'Deliverability',
            'icon' => 'fa-tasks-alt',
            'items' => [
                ['label' => 'Dashboard', 'url' => '/admin/deliverability', 'icon' => 'fa-gauge-high'],
                ['label' => 'All Deliverables', 'url' => '/admin/deliverability/list', 'icon' => 'fa-list'],
                ['label' => 'Create New', 'url' => '/admin/deliverability/create', 'icon' => 'fa-plus'],
                ['label' => 'Analytics', 'url' => '/admin/deliverability/analytics', 'icon' => 'fa-chart-line'],
            ],
        ],
    ];
}

/**
 * Filter admin modules based on conditions
 *
 * @param array $modules The modules array
 * @return array Filtered modules
 */
function filterAdminModules(array $modules): array
{
    return array_filter($modules, function($module) {
        if (!isset($module['condition'])) {
            return true;
        }

        if ($module['condition'] === 'federation') {
            return \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
        }

        return true;
    });
}

/**
 * Check if a navigation item is active
 *
 * @param string $itemUrl The item URL
 * @param string $currentPath Current request path
 * @param string $basePath Tenant base path
 * @return bool
 */
function isAdminNavActive(string $itemUrl, string $currentPath, string $basePath): bool
{
    $fullUrl = $basePath . $itemUrl;
    $currentClean = strtok($currentPath, '?');

    if ($currentClean === $fullUrl) {
        return true;
    }

    if ($itemUrl === '/admin') {
        return $currentClean === $fullUrl;
    }

    return strpos($currentClean, $fullUrl) === 0;
}

/**
 * Get the active admin module key
 *
 * @param array $modules Admin modules
 * @param string $currentPath Current request path
 * @param string $basePath Tenant base path
 * @return string Active module key
 */
function getActiveAdminModule(array $modules, string $currentPath, string $basePath): string
{
    foreach ($modules as $key => $module) {
        if (isset($module['single']) && $module['single']) {
            if (isAdminNavActive($module['url'], $currentPath, $basePath)) {
                return $key;
            }
        } elseif (isset($module['mega']) && $module['mega']) {
            foreach ($module['columns'] as $column) {
                foreach ($column['items'] as $item) {
                    if (isAdminNavActive($item['url'], $currentPath, $basePath)) {
                        return $key;
                    }
                }
            }
        } elseif (isset($module['items'])) {
            foreach ($module['items'] as $item) {
                if (isAdminNavActive($item['url'], $currentPath, $basePath)) {
                    return $key;
                }
            }
        }
    }

    return 'dashboard';
}

/**
 * Generate breadcrumbs for admin navigation
 *
 * @param array $modules Admin modules
 * @param string $currentPath Current request path
 * @param string $basePath Tenant base path
 * @param string $pageTitle Optional page title
 * @return array Breadcrumbs array
 */
function generateAdminBreadcrumbs(array $modules, string $currentPath, string $basePath, string $pageTitle = ''): array
{
    $breadcrumbs = [['label' => 'Admin', 'url' => $basePath . '/admin', 'icon' => 'fa-gauge-high']];
    $currentClean = strtok($currentPath, '?');

    foreach ($modules as $key => $module) {
        if (isset($module['single']) && $module['single']) {
            if (isAdminNavActive($module['url'], $currentPath, $basePath)) {
                if ($module['url'] !== '/admin') {
                    $breadcrumbs[] = ['label' => $module['label'], 'url' => $basePath . $module['url'], 'icon' => $module['icon']];
                }
                break;
            }
        } elseif (isset($module['mega']) && $module['mega']) {
            foreach ($module['columns'] as $column) {
                foreach ($column['items'] as $item) {
                    if (isAdminNavActive($item['url'], $currentPath, $basePath)) {
                        $breadcrumbs[] = ['label' => $module['label'], 'url' => null, 'icon' => $module['icon']];
                        if (!empty($column['title'])) {
                            $breadcrumbs[] = ['label' => $column['title'], 'url' => null, 'icon' => $item['icon']];
                        }
                        $breadcrumbs[] = ['label' => $item['label'], 'url' => $basePath . $item['url'], 'icon' => $item['icon']];
                        break 3;
                    }
                }
            }
        } elseif (isset($module['items'])) {
            foreach ($module['items'] as $item) {
                if (isAdminNavActive($item['url'], $currentPath, $basePath)) {
                    $breadcrumbs[] = ['label' => $module['label'], 'url' => null, 'icon' => $module['icon']];
                    $breadcrumbs[] = ['label' => $item['label'], 'url' => $basePath . $item['url'], 'icon' => $item['icon']];
                    break 2;
                }
            }
        }
    }

    // Check for edit/create pages
    if (preg_match('/\/(edit|create)(?:\/(\d+))?$/', $currentClean, $matches)) {
        $action = $matches[1];
        $breadcrumbs[] = ['label' => ucfirst($action), 'url' => null, 'icon' => $action === 'edit' ? 'fa-pen' : 'fa-plus'];
    }

    return $breadcrumbs;
}
