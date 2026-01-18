<?php
/**
 * Optimized Font Loading Strategy
 * Uses font-display: optional for zero layout shift
 * Preloads critical fonts for faster FCP
 */
?>

<!-- Preload critical fonts -->
<link rel="preload" href="/assets/fonts/system-fallback.woff2" as="font" type="font/woff2" crossorigin>

<!-- Font face declarations with optimal display strategy -->
<style>
    /**
     * System Font Stack - Primary (No loading required)
     * Best performance, zero network cost
     */
    :root {
        --font-system: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                       "Helvetica Neue", Arial, sans-serif,
                       "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
    }

    body {
        font-family: var(--font-system);
    }

    /**
     * Outfit (Display font) - Optional loading
     * Will only be used if it loads within 100ms, otherwise fallback to system
     */
    @font-face {
        font-family: 'Outfit';
        src: url('/assets/fonts/Outfit-Variable.woff2') format('woff2-variations');
        font-weight: 300 700;
        font-style: normal;
        font-display: optional; /* Zero layout shift */
    }

    /* Only apply Outfit to specific headings if it loaded */
    .font-display,
    h1, h2, h3 {
        font-family: 'Outfit', var(--font-system);
    }

    /**
     * Inter (Alternative) - Swap strategy
     * Use if you need consistent branding, accepts FOUT
     */
    @font-face {
        font-family: 'Inter';
        src: url('/assets/fonts/Inter-Variable.woff2') format('woff2-variations');
        font-weight: 100 900;
        font-style: normal;
        font-display: swap; /* Allow flash of unstyled text */
        /* Only loaded on specific pages that need it */
    }
</style>

<!-- Font loading detection script (optional) -->
<script>
    /**
     * Detect if custom font loaded successfully
     * Adds class to <html> for progressive enhancement
     */
    (function() {
        // Check if Outfit loaded
        if (document.fonts && document.fonts.check) {
            document.fonts.ready.then(function() {
                if (document.fonts.check('1em Outfit')) {
                    document.documentElement.classList.add('font-outfit-loaded');
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
 * Performance Notes:
 *
 * 1. System fonts = 0ms load time, perfect compatibility
 * 2. font-display: optional = Zero layout shift (CLS)
 * 3. Preload only critical fonts in <head>
 * 4. Use variable fonts (1 file = all weights)
 * 5. WOFF2 format = ~30% smaller than WOFF
 *
 * Recommended approach:
 * - Use system fonts for body text (99% of content)
 * - Use Outfit only for large headings where brand matters
 * - Accept that some users won't see Outfit (mobile, slow networks)
 * - This is OK! System fonts look great.
 *
 * Alternative for 100/100:
 * Remove ALL custom fonts, use only system stack
 * This gives perfect scores but less unique branding
 */
?>
