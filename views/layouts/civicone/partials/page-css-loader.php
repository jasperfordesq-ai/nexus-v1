<?php
/**
 * CivicOne Page-Specific CSS Loader
 * Centralizes all conditional CSS loading based on current route
 * Follows GOV.UK Design System patterns (WCAG 2.1 AA)
 *
 * Variables expected:
 * - $normPath: normalized URL path
 * - $isHome: boolean for home page
 * - $cssVersion: cache-busting version from deployment-version.php
 *
 * Created: 2026-01-25 (Phase 3 CSS Architecture Refactoring)
 */

// =============================================================================
// PAGE-SPECIFIC CSS CONFIGURATION FOR CIVICONE
// Each entry: 'section_name' => ['condition' => bool, 'files' => [...]]
// =============================================================================

$pageSpecificCSS = [
    // Home page
    'home' => [
        'condition' => $isHome,
        'files' => [
            'feed-filter.css',
            'civicone-home.css'
        ]
    ],

    // Dashboard
    'dashboard' => [
        'condition' => strpos($normPath, '/dashboard') !== false,
        'files' => [
            'dashboard.css',
            'civicone-dashboard.css'
        ]
    ],

    // Dashboard Nexus Score tab
    'dashboard-nexus-score' => [
        'condition' => strpos($normPath, '/dashboard') !== false && (isset($_GET['tab']) && $_GET['tab'] === 'nexus-score'),
        'files' => ['civicone-dashboard-nexus-score.css']
    ],

    // Members Directory
    'members' => [
        'condition' => strpos($normPath, '/members') !== false,
        'files' => [
            'moj-filter.css',
            'members-directory-v1.6.css',
            'civicone-members-directory.css'
        ]
    ],

    // Auth pages (login, register, password)
    'auth' => [
        'condition' => preg_match('/\/(login|register|password)/', $normPath),
        'files' => [
            'auth.css',
            'civicone-auth.css'
        ]
    ],

    // Feed/Post/Profile pages - post cards
    'post-cards' => [
        'condition' => $isHome || strpos($normPath, '/feed') !== false || strpos($normPath, '/profile') !== false || strpos($normPath, '/post') !== false,
        'files' => [
            'civicone-feed-item.css',
            'civicone-shared-post-card.css'
        ]
    ],

    // Feed page
    'feed' => [
        'condition' => strpos($normPath, '/feed') !== false,
        'files' => ['civicone-feed.css']
    ],

    // Feed/Post show (single item view)
    'feed-show' => [
        'condition' => preg_match('/\/feed\/\d+$/', $normPath) || preg_match('/\/post\/\d+$/', $normPath),
        'files' => ['civicone-feed-show.css']
    ],

    // Profile pages
    'profile' => [
        'condition' => strpos($normPath, '/profile') !== false,
        'files' => [
            'civicone-profile.css',
            'civicone-profile-show.css'
        ]
    ],

    // Profile edit
    'profile-edit' => [
        'condition' => strpos($normPath, '/profile/edit') !== false,
        'files' => ['civicone-profile-edit.css']
    ],

    // Messages
    'messages' => [
        'condition' => strpos($normPath, '/messages') !== false,
        'files' => [
            'civicone-messages.css',
            'civicone-messages-index.css'
        ]
    ],

    // Groups index
    'groups-index' => [
        'condition' => ($normPath === '/groups' || preg_match('/\/groups$/', $normPath)) || strpos($normPath, '/community-groups') !== false,
        'files' => [
            'civicone-groups.css'
        ]
    ],

    // Groups show (detail view)
    'groups-show' => [
        'condition' => preg_match('/\/groups\/\d+$/', $normPath),
        'files' => ['civicone-groups-show.css']
    ],

    // Groups discussions
    'groups-discussions' => [
        'condition' => strpos($normPath, '/groups/') !== false && strpos($normPath, '/discussions') !== false,
        'files' => [
            'civicone-groups-discussions-show.css',
            'civicone-groups-discussions-create.css'
        ]
    ],

    // Groups edit
    'groups-edit' => [
        'condition' => strpos($normPath, '/groups/') !== false && strpos($normPath, '/edit') !== false,
        'files' => ['civicone-groups-edit.css']
    ],

    // Groups create
    'groups-create' => [
        'condition' => strpos($normPath, '/groups/create') !== false,
        'files' => ['civicone-groups-create-overlay.css']
    ],

    // Groups my-groups
    'groups-my' => [
        'condition' => strpos($normPath, '/my-groups') !== false,
        'files' => ['civicone-groups-my-groups.css']
    ],

    // Events index
    'events-index' => [
        'condition' => $normPath === '/events' || preg_match('/\/events$/', $normPath),
        'files' => ['civicone-events.css']
    ],

    // Events calendar
    'events-calendar' => [
        'condition' => strpos($normPath, '/events/calendar') !== false,
        'files' => ['civicone-events-calendar.css']
    ],

    // Events show (detail view)
    'events-show' => [
        'condition' => preg_match('/\/events\/\d+$/', $normPath),
        'files' => ['civicone-events-show.css']
    ],

    // Listings index
    'listings-index' => [
        'condition' => $normPath === '/listings' || preg_match('/\/listings$/', $normPath),
        'files' => ['civicone-listings-directory.css']
    ],

    // Listings show (detail view)
    'listings-show' => [
        'condition' => preg_match('/\/listings\/\d+$/', $normPath),
        'files' => ['civicone-listings-directory.css']
    ],

    // Volunteering index
    'volunteering-index' => [
        'condition' => $normPath === '/volunteering' || preg_match('/\/volunteering$/', $normPath),
        'files' => ['civicone-volunteering.css']
    ],

    // Volunteering show
    'volunteering-show' => [
        'condition' => preg_match('/\/volunteering\/\d+$/', $normPath),
        'files' => ['civicone-volunteering.css']
    ],

    // Volunteering create
    'volunteering-create' => [
        'condition' => strpos($normPath, '/volunteering/create') !== false,
        'files' => ['civicone-volunteering-create-opp.css']
    ],

    // Volunteering dashboard
    'volunteering-dashboard' => [
        'condition' => strpos($normPath, '/volunteering/dashboard') !== false,
        'files' => ['civicone-volunteering-dashboard.css']
    ],

    // Blog/News
    'blog' => [
        'condition' => strpos($normPath, '/news') !== false || strpos($normPath, '/blog') !== false,
        'files' => ['civicone-blog.css']
    ],

    // Federation
    'federation' => [
        'condition' => strpos($normPath, '/federation') !== false,
        'files' => [
            'civicone-federation.css',
            'civicone-federation-shell.css'
        ]
    ],

    // Goals
    'goals' => [
        'condition' => strpos($normPath, '/goals') !== false,
        'files' => ['civicone-goals-show.css']
    ],

    // Goals edit
    'goals-edit' => [
        'condition' => strpos($normPath, '/goals/') !== false && strpos($normPath, '/edit') !== false,
        'files' => [
            'civicone-goals-edit.css',
            'civicone-goals-form.css'
        ]
    ],

    // Goals delete
    'goals-delete' => [
        'condition' => strpos($normPath, '/goals/') !== false && strpos($normPath, '/delete') !== false,
        'files' => ['civicone-goals-delete.css']
    ],

    // Polls edit
    'polls-edit' => [
        'condition' => strpos($normPath, '/polls/') !== false && strpos($normPath, '/edit') !== false,
        'files' => ['civicone-polls-edit.css']
    ],

    // Matches
    'matches' => [
        'condition' => strpos($normPath, '/matches') !== false,
        'files' => ['civicone-matches.css']
    ],

    // Leaderboard
    'leaderboard' => [
        'condition' => strpos($normPath, '/leaderboard') !== false,
        'files' => ['civicone-leaderboard.css']
    ],

    // Achievements
    'achievements' => [
        'condition' => strpos($normPath, '/achievements') !== false,
        'files' => ['civicone-achievements.css']
    ],

    // Help
    'help' => [
        'condition' => strpos($normPath, '/help') !== false,
        'files' => ['civicone-help.css']
    ],

    // Wallet
    'wallet' => [
        'condition' => strpos($normPath, '/wallet') !== false,
        'files' => ['civicone-wallet.css']
    ],

    // Settings
    'settings' => [
        'condition' => strpos($normPath, '/settings') !== false,
        'files' => ['civicone-settings.css']
    ],

    // Search
    'search' => [
        'condition' => strpos($normPath, '/search') !== false,
        'files' => ['civicone-search-results.css']
    ],

    // AI Assistant
    'ai' => [
        'condition' => strpos($normPath, '/ai') !== false,
        'files' => ['civicone-ai-index.css']
    ],

    // Onboarding
    'onboarding' => [
        'condition' => strpos($normPath, '/onboarding') !== false,
        'files' => ['civicone-onboarding-index.css']
    ],

    // Organizations
    'organizations' => [
        'condition' => strpos($normPath, '/organizations') !== false,
        'files' => [
            'civicone-org-ui-components.css',
            'civicone-organizations-utility-bar.css'
        ]
    ],

    // Organizations members
    'organizations-members' => [
        'condition' => strpos($normPath, '/organizations/') !== false && strpos($normPath, '/members') !== false,
        'files' => ['civicone-organizations-members.css']
    ],

    // Organizations wallet
    'organizations-wallet' => [
        'condition' => strpos($normPath, '/organizations/') !== false && strpos($normPath, '/wallet') !== false,
        'files' => ['civicone-organizations-wallet.css']
    ],

    // Organizations audit log
    'organizations-audit' => [
        'condition' => strpos($normPath, '/organizations/') !== false && strpos($normPath, '/audit') !== false,
        'files' => ['civicone-organizations-audit-log.css']
    ],

    // Organizations transfer requests
    'organizations-transfers' => [
        'condition' => strpos($normPath, '/organizations/') !== false && strpos($normPath, '/transfer') !== false,
        'files' => ['civicone-organizations-transfer-requests.css']
    ],

    // Resources
    'resources' => [
        'condition' => strpos($normPath, '/resources') !== false,
        'files' => ['civicone-resources-form.css']
    ],

    // Reviews
    'reviews' => [
        'condition' => strpos($normPath, '/reviews') !== false,
        'files' => ['civicone-reviews-create.css']
    ],

    // Nexus Impact Report
    'impact-report' => [
        'condition' => strpos($normPath, '/nexus-impact-report') !== false || strpos($normPath, '/impact-report') !== false,
        'files' => [
            'civicone-nexus-impact-report.css',
            'civicone-report-pages.css'
        ]
    ],

    // Impact Summary
    'impact-summary' => [
        'condition' => strpos($normPath, '/impact-summary') !== false,
        'files' => ['civicone-impact-summary.css']
    ],

    // Static pages - About/Story
    'about-story' => [
        'condition' => strpos($normPath, '/our-story') !== false || strpos($normPath, '/about-story') !== false,
        'files' => [
            'civicone-our-story.css',
            'civicone-about-story.css'
        ]
    ],

    // Static pages - How it works
    'how-it-works' => [
        'condition' => strpos($normPath, '/how-it-works') !== false,
        'files' => ['civicone-how-it-works.css']
    ],

    // Static pages - Partner
    'partner' => [
        'condition' => strpos($normPath, '/partner') !== false,
        'files' => ['civicone-partner.css']
    ],

    // Static pages - FAQ
    'faq' => [
        'condition' => strpos($normPath, '/faq') !== false || strpos($normPath, '/timebanking-faq') !== false,
        'files' => ['civicone-faq.css']
    ],

    // Static pages - Contact
    'contact' => [
        'condition' => strpos($normPath, '/contact') !== false,
        'files' => ['civicone-contact.css']
    ],

    // Static pages - Privacy (GOV.UK compliant - uses standard govuk classes)
    'privacy' => [
        'condition' => strpos($normPath, '/privacy') !== false,
        'files' => ['civicone-privacy.css']
    ],

    // Static pages - Terms (GOV.UK compliant - uses standard govuk classes)
    'terms' => [
        'condition' => strpos($normPath, '/terms') !== false,
        'files' => ['civicone-pages-legal.css']
    ],

    // Static pages - Legal pages
    'legal' => [
        'condition' => strpos($normPath, '/legal') !== false,
        'files' => [
            'civicone-legal-hub.css',
            'civicone-pages-legal.css'
        ]
    ],

    // Volunteer license
    'volunteer-license' => [
        'condition' => strpos($normPath, '/volunteer-license') !== false,
        'files' => ['civicone-legal-volunteer-license.css']
    ],

    // Consent decline
    'consent-decline' => [
        'condition' => strpos($normPath, '/consent-decline') !== false,
        'files' => ['civicone-consent-decline.css']
    ],

    // Master admin dashboard
    'master-dashboard' => [
        'condition' => strpos($normPath, '/master') !== false && strpos($normPath, '/dashboard') !== false,
        'files' => ['civicone-master-dashboard.css']
    ],

    // Master admin users
    'master-users' => [
        'condition' => strpos($normPath, '/master') !== false && strpos($normPath, '/users') !== false,
        'files' => ['civicone-master-users.css']
    ],

    // Master admin edit tenant
    'master-edit-tenant' => [
        'condition' => strpos($normPath, '/master') !== false && strpos($normPath, '/edit-tenant') !== false,
        'files' => ['civicone-master-edit-tenant.css']
    ],

    // Demo pages
    'demo' => [
        'condition' => strpos($normPath, '/demo') !== false,
        'files' => ['civicone-demo-pages.css']
    ]
];

// =============================================================================
// OUTPUT CSS LINKS
// =============================================================================
?>
    <!-- Page-Specific CSS (Conditional Loading - CivicOne) -->
<?php foreach ($pageSpecificCSS as $section => $config): ?>
<?php if ($config['condition']): ?>
<?php foreach ($config['files'] as $cssFile): ?>
    <link rel="stylesheet" href="/assets/css/<?= $cssFile ?>?v=<?= $cssVersion ?>">
<?php endforeach; ?>
<?php endif; ?>
<?php endforeach; ?>
