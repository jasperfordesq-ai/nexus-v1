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
    // Home page - Updated Phase 4 CSS Refactoring 2026-01-25
    'home' => [
        'condition' => $isHome,
        'files' => [
            'nexus-home.css',
            'post-box-home.css',
            'feed-filter.css',
            'feed-empty-state.css',
            'sidebar.css'
        ]
    ],

    // Dashboard
    'dashboard' => [
        'condition' => strpos($normPath, '/dashboard') !== false,
        'files' => [
            'dashboard.css',
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
        'files' => ['auth.css']
    ],

    // Feed/Post/Profile pages - post cards
    'post-cards' => [
        'condition' => $isHome || strpos($normPath, '/feed') !== false || strpos($normPath, '/profile') !== false || strpos($normPath, '/post') !== false,
        'files' => [
            'post-card.css',
            'feed-item.css'
        ]
    ],

    // Feed page
    'feed' => [
        'condition' => strpos($normPath, '/feed') !== false,
        'files' => ['feed-page.css']
    ],

    // Feed/Post show (single item view)
    'feed-show' => [
        'condition' => preg_match('/\/feed\/\d+$/', $normPath) || preg_match('/\/post\/\d+$/', $normPath),
        'files' => ['feed-show.css']
    ],

    // Profile edit
    'profile-edit' => [
        'condition' => strpos($normPath, '/profile/edit') !== false,
        'files' => ['profile-edit.css']
    ],

    // Messages
    'messages' => [
        'condition' => strpos($normPath, '/messages') !== false,
        'files' => ['messages-index.css']
    ],

    // Messages thread (detail view)
    'messages-thread' => [
        'condition' => preg_match('#/messages/(\d+|thread/)#', $normPath),
        'files' => ['messages-thread.css']
    ],

    // Notifications
    'notifications' => [
        'condition' => strpos($normPath, '/notifications') !== false,
        'files' => ['notifications.css']
    ],

    // Groups index/show
    'groups-show' => [
        'condition' => ($normPath === '/groups' || preg_match('/\/groups$/', $normPath)) || preg_match('/\/groups\/\d+$/', $normPath),
        'files' => [
            'groups-show.css',
            'modern-groups-show.css'
        ]
    ],

    // Events index
    'events-index' => [
        'condition' => $normPath === '/events' || preg_match('/\/events$/', $normPath),
        'files' => ['events-index.css']
    ],

    // Events calendar
    'events-calendar' => [
        'condition' => strpos($normPath, '/events/calendar') !== false,
        'files' => ['events-calendar.css']
    ],

    // Events create
    'events-create' => [
        'condition' => strpos($normPath, '/events/create') !== false,
        'files' => ['events-create.css']
    ],

    // Events show (detail view)
    'events-show' => [
        'condition' => preg_match('/\/events\/\d+$/', $normPath),
        'files' => [
            'events-show.css',
            'modern-events-show.css'
        ]
    ],

    // Blog/News index
    'blog-index' => [
        'condition' => $normPath === '/news' || $normPath === '/blog' || preg_match('/\/(news|blog)$/', $normPath),
        'files' => ['blog-index.css']
    ],

    // Blog/News show (detail view)
    'blog-show' => [
        'condition' => preg_match('/\/(news|blog)\/[^\/]+$/', $normPath) && !preg_match('/\/(news|blog)\/(create|edit)/', $normPath),
        'files' => ['blog-show.css']
    ],

    // Listings index
    'listings-index' => [
        'condition' => $normPath === '/listings' || preg_match('/\/listings$/', $normPath),
        'files' => ['listings-index.css']
    ],

    // Listings create
    'listings-create' => [
        'condition' => strpos($normPath, '/listings/create') !== false,
        'files' => ['listings-create.css']
    ],

    // Listings show (detail view)
    'listings-show' => [
        'condition' => preg_match('/\/listings\/\d+$/', $normPath),
        'files' => ['listings-show.css']
    ],

    // Federation/Transactions
    'federation' => [
        'condition' => strpos($normPath, '/federation') !== false || strpos($normPath, '/transactions') !== false,
        'files' => ['federation.css']
    ],

    // Volunteering
    'volunteering' => [
        'condition' => strpos($normPath, '/volunteering') !== false,
        'files' => [
            'volunteering.css',
            'modern-volunteering-show.css'
        ]
    ],

    // Groups (all routes)
    'groups-all' => [
        'condition' => strpos($normPath, '/groups') !== false || strpos($normPath, '/edit-group') !== false,
        'files' => ['groups.css']
    ],

    // Goals
    'goals' => [
        'condition' => strpos($normPath, '/goals') !== false,
        'files' => ['goals.css']
    ],

    // Polls
    'polls' => [
        'condition' => strpos($normPath, '/polls') !== false,
        'files' => ['polls.css']
    ],

    // Resources
    'resources' => [
        'condition' => strpos($normPath, '/resources') !== false,
        'files' => ['resources.css']
    ],

    // Matches
    'matches' => [
        'condition' => strpos($normPath, '/matches') !== false,
        'files' => ['matches.css']
    ],

    // Organizations
    'organizations' => [
        'condition' => strpos($normPath, '/organizations') !== false,
        'files' => ['organizations.css']
    ],

    // Help
    'help' => [
        'condition' => strpos($normPath, '/help') !== false,
        'files' => ['help.css']
    ],

    // Wallet
    'wallet' => [
        'condition' => strpos($normPath, '/wallet') !== false,
        'files' => ['wallet.css']
    ],

    // Static pages
    'static-pages' => [
        'condition' => preg_match('/\/(about|accessibility|contact|how-it-works|legal|mobile-about|partner|privacy|terms|timebanking-guide)/', $normPath),
        'files' => ['static-pages.css']
    ],

    // Scattered singles (ai, connections, leaderboard, members, etc.)
    'scattered-singles' => [
        'condition' => preg_match('/\/(ai|connections|forgot-password|leaderboard|volunteer-license|members|onboarding|nexus-impact-report|reviews|search|settings|master)/', $normPath)
                       || (strpos($normPath, '/events') !== false && strpos($normPath, 'edit') !== false)
                       || (strpos($normPath, '/listings') !== false && strpos($normPath, 'edit') !== false),
        'files' => ['scattered-singles.css']
    ],

    // Settings
    'settings' => [
        'condition' => strpos($normPath, '/settings') !== false,
        'files' => ['modern-settings.css']
    ],

    // Search results
    'search' => [
        'condition' => strpos($normPath, '/search') !== false,
        'files' => ['modern-search-results.css', 'search-results.css']
    ],

    // Terms page
    'terms' => [
        'condition' => strpos($normPath, '/terms') !== false,
        'files' => ['terms-page.css']
    ],

    // Achievements (all pages) - Added Phase 4 CSS Refactoring 2026-01-25
    'achievements' => [
        'condition' => strpos($normPath, '/achievements') !== false,
        'files' => ['achievements.css']
    ],

    // Profile show (detail view) - Added Phase 4 CSS Refactoring 2026-01-25
    'profile-show' => [
        'condition' => preg_match('/\/profile\/[^\/]+$/', $normPath) && strpos($normPath, '/edit') === false,
        'files' => [
            'profile-holographic.css',
            'modern-profile-show.css'
        ]
    ],

    // Groups index - Added Phase 4 CSS Refactoring 2026-01-25
    'groups-index' => [
        'condition' => $normPath === '/groups' || preg_match('/\/groups$/', $normPath),
        'files' => ['nexus-groups.css']
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
