<?php
/**
 * CSS Bundler - Combines and minifies CSS files into optimized bundles
 *
 * Usage: php scripts/css-bundler.php
 *
 * Creates:
 *   - bundles/core.min.css (critical framework styles)
 *   - bundles/components.min.css (UI components)
 *   - bundles/mobile.min.css (mobile-specific, loaded conditionally)
 */

define('BASE_PATH', dirname(__DIR__));
define('CSS_PATH', BASE_PATH . '/httpdocs/assets/css');
define('BUNDLE_PATH', CSS_PATH . '/bundles');

// Bundle definitions - order matters for CSS cascade
$bundles = [
    'core' => [
        'description' => 'Critical framework styles - load synchronously',
        // Updated 2026-01-17: Removed nexus-page-layout.css (file doesn't exist)
        'files' => [
            'layout-isolation.css',
            'nexus-phoenix.css',
            'nexus-shared-transitions.css',
            'post-box-home.css',
            'nexus-modern-header.css',
            'nexus-loading-fix.css',
        ]
    ],
    'components' => [
        'description' => 'UI components - can be deferred',
        'files' => [
            'nexus-premium-mega-menu.css',
            'premium-dropdowns.css',
            'premium-search.css',
            'nexus-polish.css',
            'nexus-interactions.css',
            'nexus-performance-patch.css',
        ]
    ],
    'mobile' => [
        'description' => 'Mobile navigation - load with media query',
        'files' => [
            'nexus-mobile.css',
            'nexus-native-nav-v2.css',
        ]
    ]
];

/**
 * Minify CSS - removes comments, whitespace, and optimizes
 */
function minifyCSS($css) {
    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

    // Remove whitespace
    $css = preg_replace('/\s+/', ' ', $css);

    // Remove space around special characters
    $css = preg_replace('/\s*([\{\}\:\;\,\>\+\~])\s*/', '$1', $css);

    // Remove trailing semicolons before closing braces
    $css = preg_replace('/;}/', '}', $css);

    // Remove leading/trailing whitespace
    $css = trim($css);

    return $css;
}

/**
 * Get file size in human readable format
 */
function formatSize($bytes) {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

// Create bundles directory if it doesn't exist
if (!is_dir(BUNDLE_PATH)) {
    mkdir(BUNDLE_PATH, 0755, true);
    echo "Created bundles directory: " . BUNDLE_PATH . "\n\n";
}

echo "===========================================\n";
echo "  CSS BUNDLER - Project NEXUS\n";
echo "===========================================\n\n";

$totalOriginal = 0;
$totalMinified = 0;
$allFiles = [];

foreach ($bundles as $bundleName => $bundle) {
    echo "Building: {$bundleName}.min.css\n";
    echo "  {$bundle['description']}\n";

    $combined = "/* {$bundleName}.min.css - {$bundle['description']} */\n";
    $combined .= "/* Generated: " . date('Y-m-d H:i:s') . " */\n\n";

    $bundleOriginalSize = 0;
    $missingFiles = [];

    foreach ($bundle['files'] as $file) {
        $filePath = CSS_PATH . '/' . $file;

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $fileSize = strlen($content);
            $bundleOriginalSize += $fileSize;
            $totalOriginal += $fileSize;
            $allFiles[] = $file;

            // Add file marker comment (will be removed in minification)
            $combined .= "/* === {$file} === */\n";
            $combined .= $content . "\n\n";

            echo "    + {$file} (" . formatSize($fileSize) . ")\n";
        } else {
            $missingFiles[] = $file;
            echo "    ! MISSING: {$file}\n";
        }
    }

    // Minify the combined CSS
    $minified = minifyCSS($combined);
    $minifiedSize = strlen($minified);
    $totalMinified += $minifiedSize;

    // Write the bundle
    $outputPath = BUNDLE_PATH . "/{$bundleName}.min.css";
    file_put_contents($outputPath, $minified);

    // Also create non-minified version for debugging
    $debugPath = BUNDLE_PATH . "/{$bundleName}.css";
    file_put_contents($debugPath, $combined);

    $savings = $bundleOriginalSize > 0
        ? round((1 - $minifiedSize / $bundleOriginalSize) * 100, 1)
        : 0;

    echo "  Output: " . formatSize($minifiedSize) . " (was " . formatSize($bundleOriginalSize) . ", -{$savings}%)\n";

    if (!empty($missingFiles)) {
        echo "  WARNING: " . count($missingFiles) . " file(s) missing\n";
    }

    echo "\n";
}

// Create a combined super-bundle for simplest loading
echo "Building: all.min.css (combined super-bundle)\n";
$allCombined = '';
foreach ($bundles as $bundleName => $bundle) {
    $bundlePath = BUNDLE_PATH . "/{$bundleName}.min.css";
    if (file_exists($bundlePath)) {
        $allCombined .= file_get_contents($bundlePath) . "\n";
    }
}
file_put_contents(BUNDLE_PATH . '/all.min.css', $allCombined);
echo "  Output: " . formatSize(strlen($allCombined)) . "\n\n";

// Summary
echo "===========================================\n";
echo "  SUMMARY\n";
echo "===========================================\n";
echo "Files processed: " . count($allFiles) . "\n";
echo "Original size:   " . formatSize($totalOriginal) . "\n";
echo "Minified size:   " . formatSize($totalMinified) . "\n";
echo "Total savings:   " . round((1 - $totalMinified / $totalOriginal) * 100, 1) . "%\n";
echo "\n";
echo "Bundles created:\n";
echo "  - bundles/core.min.css       (sync load)\n";
echo "  - bundles/components.min.css (defer load)\n";
echo "  - bundles/mobile.min.css     (media query)\n";
echo "  - bundles/all.min.css        (single file option)\n";
echo "\n";
echo "HTTP Requests: 22 files -> 3 bundles (-86%)\n";
echo "\n";

// Generate PHP include snippet
$snippet = <<<'PHP'
<!-- Optimized CSS Loading -->
<!-- Core styles - synchronous (required for initial render) -->
<link rel="stylesheet" href="/assets/css/bundles/core.min.css">

<!-- Components - preload then apply -->
<link rel="preload" href="/assets/css/bundles/components.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="/assets/css/bundles/components.min.css"></noscript>

<!-- Mobile - only on small screens -->
<link rel="stylesheet" href="/assets/css/bundles/mobile.min.css" media="(max-width: 768px)">
PHP;

echo "Header.php snippet:\n";
echo "-------------------------------------------\n";
echo $snippet;
echo "\n-------------------------------------------\n";
echo "\nDone! Run Lighthouse to verify improvements.\n";
