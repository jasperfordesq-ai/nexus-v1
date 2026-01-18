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

// Include the full CivicOne feed as the home page
require __DIR__ . '/feed/index.php';
