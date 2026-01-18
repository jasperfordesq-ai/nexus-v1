<?php
/**
 * CivicOne Optimized Font Loading Strategy
 * Uses font-display: optional for zero layout shift
 * Preloads critical fonts for faster FCP
 * Government/Public Sector appropriate typography
 */
?>

<!-- Preload critical fonts -->
<link rel="preload" href="/assets/fonts/system-fallback.woff2" as="font" type="font/woff2" crossorigin>

<!-- Font face declarations with optimal display strategy -->
<style>
    /**
     * System Font Stack - Primary (No loading required)
     * Best performance, zero network cost
     * Government-appropriate typography
     */
    :root {
        --font-system: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI",
                       "Helvetica Neue", Arial, sans-serif,
                       "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        --font-civic: var(--font-system);
    }

    body {
        font-family: var(--font-system);
    }

    /**
     * Roboto (Primary font) - Optional loading
     * Clean, professional, government-appropriate
     */
    @font-face {
        font-family: 'Roboto';
        src: url('/assets/fonts/Roboto-Variable.woff2') format('woff2-variations'),
             local('Roboto'),
             local('Roboto-Regular');
        font-weight: 300 700;
        font-style: normal;
        font-display: optional; /* Zero layout shift */
    }

    /* Headings - Professional government style */
    .font-display,
    h1, h2, h3 {
        font-family: 'Roboto', var(--font-system);
        font-weight: 600;
    }

    /**
     * Source Sans Pro (Alternative) - For forms and data
     */
    @font-face {
        font-family: 'Source Sans Pro';
        src: url('/assets/fonts/SourceSansPro-Variable.woff2') format('woff2-variations'),
             local('Source Sans Pro');
        font-weight: 400 700;
        font-style: normal;
        font-display: swap;
    }

    /* Government document styles */
    .civic-document,
    .civic-form {
        font-family: 'Source Sans Pro', var(--font-system);
    }
</style>

<!-- Font loading detection script (optional) -->
<script>
    /**
     * Detect if custom font loaded successfully
     * Adds class to <html> for progressive enhancement
     */
    (function() {
        // Check if Roboto loaded
        if (document.fonts && document.fonts.check) {
            document.fonts.ready.then(function() {
                if (document.fonts.check('1em Roboto')) {
                    document.documentElement.classList.add('font-roboto-loaded');
                }
            });
        }

        // Fallback for older browsers
        else if ('fontDisplay' in document.documentElement.style) {
            document.documentElement.classList.add('font-display-supported');
        }
    })();
</script>

<?php
/**
 * CivicOne Font Performance Notes:
 *
 * 1. Roboto - Clean, professional, government standard
 * 2. System fonts as fallback = 0ms load time
 * 3. font-display: optional = Zero layout shift (CLS)
 * 4. WCAG 2.1 AA compliant sizing and contrast
 *
 * Government accessibility requirements:
 * - Minimum 16px body text
 * - Line height 1.5 or greater
 * - Sans-serif for screen reading
 * - High contrast ratios
 */
?>
