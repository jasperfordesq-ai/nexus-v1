<?php
/**
 * CivicOne Home - Feed View
 *
 * This redirects to the feed/index which is the proper CivicOne feed implementation.
 * The feed/index.php has the full MadeOpen-style community pulse feed with:
 * - WCAG 2.1 AA compliant design
 * - Dark mode support
 * - GDS/FDS accessibility standards
 * - Full social interactions (likes, comments, shares)
 */

// Override hero for homepage (use banner variant instead of feed's page variant)
$heroOverrides = [
    'variant' => 'banner',
    'title' => 'Welcome to Your Community',
    'lead' => 'Connect, collaborate, and make a difference in your local area.',
    'cta' => [
        'text' => 'Get started',
        'url' => '/join',
    ],
];

// Include the full CivicOne feed as the home page
require __DIR__ . '/feed/index.php';
