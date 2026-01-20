<?php
/**
 * CivicOne Layout Footer - Government/Public Sector Theme
 *
 * Refactored 2026-01-20: Extracted into partials for maintainability
 * See docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md for implementation details
 *
 * Partial Structure:
 * - main-close.php: Closes </main> tag opened in header
 * - site-footer.php: Footer content, mobile nav, mobile sheets
 * - assets-js-footer.php: All JavaScript loading (Mapbox, Nexus UI, Pusher, etc.)
 * - document-close.php: </body></html>
 */

// Close main content container (opened in header.php)
require __DIR__ . '/partials/main-close.php';

// Site footer (footer content, mobile nav, mobile sheets)
require __DIR__ . '/partials/site-footer.php';

// Footer JavaScript (Mapbox, UI, notifications, etc.)
require __DIR__ . '/partials/assets-js-footer.php';

// Document closing (</body></html>)
require __DIR__ . '/partials/document-close.php';
