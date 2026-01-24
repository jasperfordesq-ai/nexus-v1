<?php
/**
 * Modern Layout - Consolidated CSS Loader
 * Optimized loading with proper categorization
 * Updated: 2026-01-24
 *
 * Performance Notes:
 * - Critical CSS: 5 files (sync) - Required for above-fold rendering
 * - Header CSS: 7 files (sync) - Navigation must render immediately
 * - All other CSS: Async loaded via media="print" hack
 * - Mobile/Desktop CSS: Media query conditional loading
 *
 * Font Awesome: Currently loaded from CDN in header.php
 * TODO: Self-host for better performance (requires downloading webfonts)
 *
 * Categories:
 * 1. Critical (render-blocking) - Design tokens, core framework
 * 2. Header (sync) - Header, navbar, mega-menu
 * 3. Components (async) - UI components lazy-loaded
 * 4. Utilities (async) - Polish, accessibility, loading states
 * 5. Mobile (media query) - Mobile-specific styles
 * 6. Page-specific (conditional) - Loaded based on route
 */

// Use deployment version for cache busting
$cssVersion = $cssVersionTimestamp ?? time();
$assetBase = $assetBase ?? '';

// Helper function for async CSS loading (guard against redeclaration)
if (!function_exists('asyncCss')) {
    function asyncCss($href, $version, $assetBase = '') {
        $fullHref = $assetBase . $href . '?v=' . $version;
        return '<link rel="stylesheet" href="' . $fullHref . '" media="print" onload="this.media=\'all\'">';
    }
}

// Helper for sync CSS (guard against redeclaration)
if (!function_exists('syncCss')) {
    function syncCss($href, $version, $assetBase = '', $media = null) {
        $fullHref = $assetBase . $href . '?v=' . $version;
        $mediaAttr = $media ? ' media="' . $media . '"' : '';
        return '<link rel="stylesheet" href="' . $fullHref . '"' . $mediaAttr . '>';
    }
}
?>

<!-- ==========================================
     1. CRITICAL CSS (Render-blocking)
     ========================================== -->
<?= syncCss('/assets/css/design-tokens.min.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/nexus-phoenix.min.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/bundles/core.min.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/bundles/components.min.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/theme-transitions.min.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/modern-experimental-banner.min.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     2. HEADER & NAVIGATION (Sync - above fold)
     ========================================== -->
<?= syncCss('/assets/css/nexus-modern-header-v2.min.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/nexus-premium-mega-menu.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/mega-menu-icons.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/nexus-native-nav-v2.min.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/mobile-nav-v2.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/modern-header-utilities.css', $cssVersion, $assetBase) ?>

<?= syncCss('/assets/css/modern-header-emergency-fixes.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     3. PAGE-SPECIFIC CSS (Conditional)
     ========================================== -->
<?php require __DIR__ . '/page-css-loader.php'; ?>

<!-- ==========================================
     4. COMPONENTS (Async - lazy loaded)
     ========================================== -->
<?= asyncCss('/assets/css/bundles/components-navigation.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-buttons.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-forms.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-cards.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-modals.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/components-notifications.min.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     5. UTILITIES (Async - lazy loaded)
     ========================================== -->
<?= asyncCss('/assets/css/bundles/utilities-polish.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/utilities-loading.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/utilities-accessibility.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/bundles/enhancements.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/page-transitions.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/error-states.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/avatar-placeholders.min.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     6. MOBILE CSS (Media query conditional)
     ========================================== -->
<?= syncCss('/assets/css/mobile-design-tokens.min.css', $cssVersion, $assetBase, '(max-width: 768px)') ?>

<?= syncCss('/assets/css/nexus-mobile.min.css', $cssVersion, $assetBase, '(max-width: 768px)') ?>

<?= asyncCss('/assets/css/mobile-accessibility-fixes.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-loading-states.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-micro-interactions.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-sheets.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-select-sheet.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/mobile-search-overlay.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     7. DESKTOP CSS (Media query conditional)
     ========================================== -->
<?= syncCss('/assets/css/desktop-design-tokens.min.css', $cssVersion, $assetBase, '(min-width: 769px)') ?>

<?= syncCss('/assets/css/desktop-hover-system.min.css', $cssVersion, $assetBase, '(min-width: 769px)') ?>

<?= syncCss('/assets/css/desktop-loading-states.min.css', $cssVersion, $assetBase, '(min-width: 769px)') ?>


<!-- ==========================================
     8. SHARED COMPONENTS & PARTIALS (Async)
     ========================================== -->
<?= asyncCss('/assets/css/social-interactions.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/components.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/partials.min.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/modern/components-library.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/nexus-modern-footer.min.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     9. UTILITIES & POLISH (Async)
     ========================================== -->
<?= asyncCss('/assets/css/civicone-utilities.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/civicone-utilities-extended.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/nexus-performance-patch.min.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     10. MODALS & OVERLAYS (Async)
     ========================================== -->
<?= asyncCss('/assets/css/biometric-modal.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/pwa-install-modal.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     11. NATIVE APP CSS (Capacitor)
     ========================================== -->
<?= asyncCss('/assets/css/native-page-enter.css', $cssVersion, $assetBase) ?>

<?= asyncCss('/assets/css/native-form-inputs.css', $cssVersion, $assetBase) ?>


<!-- ==========================================
     12. EMERGENCY OVERRIDES (Must be last)
     ========================================== -->
<?= syncCss('/assets/css/scroll-fix-emergency.min.css', $cssVersion, $assetBase) ?>


<!-- Noscript fallbacks for critical async bundles -->
<noscript>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-navigation.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-buttons.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-forms.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/utilities-polish.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/enhancements.min.css?v=<?= $cssVersion ?>">
</noscript>
