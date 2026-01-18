<?php
/**
 * CSS & JS Minification Script
 *
 * Usage:
 *   php scripts/minify.php           # Minify all files
 *   php scripts/minify.php --css     # CSS only
 *   php scripts/minify.php --js      # JS only
 *   php scripts/minify.php --check   # Check which files need updating
 *   php scripts/minify.php --watch   # Watch mode (requires manual re-run)
 *
 * This script:
 * - Finds all .css and .js files without .min suffix
 * - Generates corresponding .min.css and .min.js files
 * - Only updates if source is newer than minified version
 *
 * Created: 2026-01-18
 */

define('CSS_DIR', __DIR__ . '/../httpdocs/assets/css');
define('JS_DIR', __DIR__ . '/../httpdocs/assets/js');

// Parse arguments
$args = array_slice($argv, 1);
$cssOnly = in_array('--css', $args);
$jsOnly = in_array('--js', $args);
$checkOnly = in_array('--check', $args);
$verbose = in_array('-v', $args) || in_array('--verbose', $args);

// If neither specified, do both
if (!$cssOnly && !$jsOnly) {
    $cssOnly = true;
    $jsOnly = true;
}

$stats = [
    'checked' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => 0
];

echo "=== Minification Script ===\n";
echo "Mode: " . ($checkOnly ? "CHECK ONLY" : "MINIFY") . "\n\n";

// Process CSS
if ($cssOnly) {
    echo "--- CSS Files ---\n";
    processDirectory(CSS_DIR, 'css', $checkOnly, $verbose, $stats);

    // Also process bundles subdirectory
    if (is_dir(CSS_DIR . '/bundles')) {
        echo "\n--- CSS Bundles ---\n";
        processDirectory(CSS_DIR . '/bundles', 'css', $checkOnly, $verbose, $stats);
    }
}

// Process JS
if ($jsOnly) {
    echo "\n--- JS Files ---\n";
    processDirectory(JS_DIR, 'js', $checkOnly, $verbose, $stats);
}

// Summary
echo "\n=== Summary ===\n";
echo "Checked: {$stats['checked']}\n";
echo "Updated: {$stats['updated']}\n";
echo "Skipped (up-to-date): {$stats['skipped']}\n";
if ($stats['errors'] > 0) {
    echo "Errors: {$stats['errors']}\n";
}

/**
 * Process all files in a directory
 */
function processDirectory($dir, $type, $checkOnly, $verbose, &$stats) {
    if (!is_dir($dir)) {
        echo "Directory not found: $dir\n";
        return;
    }

    $pattern = $dir . '/*.' . $type;
    $files = glob($pattern);

    foreach ($files as $file) {
        $basename = basename($file);

        // Skip already minified files
        if (strpos($basename, '.min.') !== false) {
            continue;
        }

        // Skip archived files
        if (strpos($file, '_archived') !== false) {
            continue;
        }

        $stats['checked']++;

        // Determine minified filename
        $minFile = str_replace('.' . $type, '.min.' . $type, $file);

        // Check if minification needed
        $needsUpdate = !file_exists($minFile) ||
                       filemtime($file) > filemtime($minFile);

        if (!$needsUpdate) {
            $stats['skipped']++;
            if ($verbose) {
                echo "  [SKIP] $basename (up-to-date)\n";
            }
            continue;
        }

        if ($checkOnly) {
            echo "  [NEEDS UPDATE] $basename\n";
            $stats['updated']++;
            continue;
        }

        // Minify the file
        $content = file_get_contents($file);

        if ($type === 'css') {
            $minified = minifyCSS($content);
        } else {
            $minified = minifyJS($content);
        }

        if ($minified === false) {
            echo "  [ERROR] $basename - Minification failed\n";
            $stats['errors']++;
            continue;
        }

        // Calculate savings
        $originalSize = strlen($content);
        $minifiedSize = strlen($minified);
        $savings = $originalSize > 0 ? round((1 - $minifiedSize / $originalSize) * 100, 1) : 0;

        // Write minified file
        if (file_put_contents($minFile, $minified) !== false) {
            echo "  [OK] $basename -> " . basename($minFile) . " (-{$savings}%)\n";
            $stats['updated']++;
        } else {
            echo "  [ERROR] $basename - Could not write file\n";
            $stats['errors']++;
        }
    }
}

/**
 * Minify CSS content
 * Simple but effective minification
 */
function minifyCSS($css) {
    // Remove comments (but preserve /*! ... */ license comments)
    $css = preg_replace('/\/\*(?!!)[\s\S]*?\*\//', '', $css);

    // Remove whitespace
    $css = preg_replace('/\s+/', ' ', $css);

    // Remove space around special characters
    $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);

    // Remove semicolon before closing brace
    $css = str_replace(';}', '}', $css);

    // Remove leading/trailing whitespace
    $css = trim($css);

    return $css;
}

/**
 * Minify JS content using terser (npm package)
 * Falls back to basic minification if terser unavailable
 */
function minifyJS($js) {
    // Try using terser (proper JS minifier)
    $terserPath = __DIR__ . '/../node_modules/.bin/terser';
    if (PHP_OS_FAMILY === 'Windows') {
        $terserPath = __DIR__ . '/../node_modules/.bin/terser.cmd';
    }

    if (file_exists($terserPath) || file_exists(__DIR__ . '/../node_modules/.bin/terser')) {
        // Write to temp file
        $tmpFile = sys_get_temp_dir() . '/minify_' . uniqid() . '.js';
        file_put_contents($tmpFile, $js);

        // Run terser
        $cmd = escapeshellarg($terserPath) . ' ' . escapeshellarg($tmpFile) . ' --compress --mangle 2>&1';
        $output = shell_exec($cmd);
        unlink($tmpFile);

        if ($output && strpos($output, 'Error') === false) {
            return trim($output);
        }
    }

    // Fallback: Simple safe minification (just remove comments and normalize whitespace)
    // Remove single-line comments (but not URLs with //)
    $js = preg_replace('#(?<!:)//(?!["\'`])(?![^"\'`]*["\'`]\s*$).*$#m', '', $js);

    // Remove multi-line comments (but preserve /*! ... */ license comments)
    $js = preg_replace('/\/\*(?!!)[\s\S]*?\*\//', '', $js);

    // Normalize whitespace but DON'T remove spaces around keywords
    $js = preg_replace('/[ \t]+/', ' ', $js);
    $js = preg_replace('/\n\s*\n/', "\n", $js);

    return trim($js);
}
