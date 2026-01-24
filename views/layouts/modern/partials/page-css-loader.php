<?php
/**
 * Page-Specific CSS Loader
 * Centralizes all conditional CSS loading based on current route
 *
 * Variables expected:
 * - $normPath: normalized URL path
 * - $isHome: boolean for home page
 * - $assetBase: asset path prefix
 * - $cssVersionTimestamp: cache-busting version
 */

// =============================================================================
// PAGE-SPECIFIC CSS CONFIGURATION
// Each entry: 'route_pattern' => ['css_files' => [...], 'match_type' => 'exact|contains|regex']
// =============================================================================

$pageSpecificCSS = [
    // Home page
    'home' => [
        'condition' => $isHome,
        'files' => [
            'post-box-home.min.css',
            'feed-filter.min.css'
        ]
    ],

    // Dashboard
    'dashboard' => [
        'condition' => strpos($normPath, '/dashboard') !== false,
        'files' => [
            'dashboard.min.css',
            'modern-dashboard.css'
        ]
    ],

    // Nexus Score
    'nexus-score' => [
        'condition' => strpos($normPath, '/nexus-score') !== false || strpos($normPath, '/score') !== false,
        'files' => ['nexus-score.css']
    ],

    // Auth pages (login, register, password)
    'auth' => [
        'condition' => preg_match('/\/(login|register|password)/', $normPath),
        'files' => ['auth.min.css']
    ],

    // Feed/Post/Profile pages - post cards
    'post-cards' => [
        'condition' => $isHome || strpos($normPath, '/feed') !== false || strpos($normPath, '/profile') !== false || strpos($normPath, '/post') !== false,
        'files' => [
            'post-card.min.css',
            'feed-item.min.css'
        ]
    ],

    // Feed page
    'feed' => [
        'condition' => strpos($normPath, '/feed') !== false,
        'files' => ['feed-page.min.css']
    ],

    // Feed/Post show (single item view)
    'feed-show' => [
        'condition' => preg_match('/\/feed\/\d+$/', $normPath) || preg_match('/\/post\/\d+$/', $normPath),
        'files' => ['feed-show.min.css']
    ],

    // Profile edit
    'profile-edit' => [
        'condition' => strpos($normPath, '/profile/edit') !== false,
        'files' => ['profile-edit.min.css']
    ],

    // Messages
    'messages' => [
        'condition' => strpos($normPath, '/messages') !== false,
        'files' => ['messages-index.min.css']
    ],

    // Messages thread (detail view)
    'messages-thread' => [
        'condition' => preg_match('#/messages/(\d+|thread/)#', $normPath),
        'files' => ['messages-thread.min.css']
    ],

    // Notifications
    'notifications' => [
        'condition' => strpos($normPath, '/notifications') !== false,
        'files' => ['notifications.min.css']
    ],

    // Groups index/show
    'groups-show' => [
        'condition' => ($normPath === '/groups' || preg_match('/\/groups$/', $normPath)) || preg_match('/\/groups\/\d+$/', $normPath),
        'files' => [
            'groups-show.min.css',
            'modern-groups-show.min.css'
        ]
    ],

    // Events index
    'events-index' => [
        'condition' => $normPath === '/events' || preg_match('/\/events$/', $normPath),
        'files' => ['events-index.min.css']
    ],

    // Events calendar
    'events-calendar' => [
        'condition' => strpos($normPath, '/events/calendar') !== false,
        'files' => ['events-calendar.min.css']
    ],

    // Events create
    'events-create' => [
        'condition' => strpos($normPath, '/events/create') !== false,
        'files' => ['events-create.min.css']
    ],

    // Events show (detail view)
    'events-show' => [
        'condition' => preg_match('/\/events\/\d+$/', $normPath),
        'files' => [
            'events-show.min.css',
            'modern-events-show.min.css'
        ]
    ],

    // Blog/News index
    'blog-index' => [
        'condition' => $normPath === '/news' || $normPath === '/blog' || preg_match('/\/(news|blog)$/', $normPath),
        'files' => ['blog-index.min.css']
    ],

    // Blog/News show (detail view)
    'blog-show' => [
        'condition' => preg_match('/\/(news|blog)\/[^\/]+$/', $normPath) && !preg_match('/\/(news|blog)\/(create|edit)/', $normPath),
        'files' => ['blog-show.min.css']
    ],

    // Listings index
    'listings-index' => [
        'condition' => $normPath === '/listings' || preg_match('/\/listings$/', $normPath),
        'files' => ['listings-index.min.css']
    ],

    // Listings create
    'listings-create' => [
        'condition' => strpos($normPath, '/listings/create') !== false,
        'files' => ['listings-create.min.css']
    ],

    // Listings show (detail view)
    'listings-show' => [
        'condition' => preg_match('/\/listings\/\d+$/', $normPath),
        'files' => ['listings-show.min.css']
    ],

    // Federation/Transactions
    'federation' => [
        'condition' => strpos($normPath, '/federation') !== false || strpos($normPath, '/transactions') !== false,
        'files' => ['federation.min.css']
    ],

    // Volunteering
    'volunteering' => [
        'condition' => strpos($normPath, '/volunteering') !== false,
        'files' => [
            'volunteering.min.css',
            'modern-volunteering-show.min.css'
        ]
    ],

    // Groups (all routes)
    'groups-all' => [
        'condition' => strpos($normPath, '/groups') !== false || strpos($normPath, '/edit-group') !== false,
        'files' => ['groups.min.css']
    ],

    // Goals
    'goals' => [
        'condition' => strpos($normPath, '/goals') !== false,
        'files' => ['goals.min.css']
    ],

    // Polls
    'polls' => [
        'condition' => strpos($normPath, '/polls') !== false,
        'files' => ['polls.min.css']
    ],

    // Resources
    'resources' => [
        'condition' => strpos($normPath, '/resources') !== false,
        'files' => ['resources.min.css']
    ],

    // Matches
    'matches' => [
        'condition' => strpos($normPath, '/matches') !== false,
        'files' => ['matches.min.css']
    ],

    // Organizations
    'organizations' => [
        'condition' => strpos($normPath, '/organizations') !== false,
        'files' => ['organizations.min.css']
    ],

    // Help
    'help' => [
        'condition' => strpos($normPath, '/help') !== false,
        'files' => ['help.min.css']
    ],

    // Wallet
    'wallet' => [
        'condition' => strpos($normPath, '/wallet') !== false,
        'files' => ['wallet.min.css']
    ],

    // Static pages
    'static-pages' => [
        'condition' => preg_match('/\/(about|accessibility|contact|how-it-works|legal|mobile-about|partner|privacy|terms|timebanking-guide)/', $normPath),
        'files' => ['static-pages.min.css']
    ],

    // Scattered singles (ai, connections, leaderboard, members, etc.)
    'scattered-singles' => [
        'condition' => preg_match('/\/(ai|connections|forgot-password|leaderboard|volunteer-license|members|onboarding|nexus-impact-report|reviews|search|settings|master)/', $normPath)
                       || (strpos($normPath, '/events') !== false && strpos($normPath, 'edit') !== false)
                       || (strpos($normPath, '/listings') !== false && strpos($normPath, 'edit') !== false),
        'files' => ['scattered-singles.min.css']
    ],

    // Settings
    'settings' => [
        'condition' => strpos($normPath, '/settings') !== false,
        'files' => ['modern-settings.css']
    ],

    // Search results
    'search' => [
        'condition' => strpos($normPath, '/search') !== false,
        'files' => ['modern-search-results.css']
    ]
];

// =============================================================================
// OUTPUT CSS LINKS
// =============================================================================
?>
    <!-- Page-Specific CSS (Conditional Loading) -->
<?php foreach ($pageSpecificCSS as $section => $config): ?>
<?php if ($config['condition']): ?>
<?php foreach ($config['files'] as $cssFile): ?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/<?= $cssFile ?>?v=<?= $cssVersionTimestamp ?>">
<?php endforeach; ?>
<?php endif; ?>
<?php endforeach; ?>
