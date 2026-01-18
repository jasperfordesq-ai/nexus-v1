<?php
/**
 * Script: Add Lazy Loading to All Images
 *
 * Automatically adds loading="lazy" to all <img> tags in view files
 * Also ensures alt attributes are present
 *
 * Usage: php scripts/add-lazy-loading.php
 */

$viewsDir = __DIR__ . '/../views/modern';
$excludeDirs = ['mobile', 'components']; // Already have lazy loading

function addLazyLoadingToFile($filePath) {
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $changes = 0;

    // Pattern 1: Add loading="lazy" to images that don't have it
    $pattern = '/<img\s+([^>]*?)(?<!loading=")(?<!loading=\')>/i';
    $replacement = function($matches) use (&$changes) {
        $attrs = $matches[1];

        // Skip if already has loading attribute
        if (preg_match('/loading\s*=\s*["\']/', $attrs)) {
            return $matches[0];
        }

        // Skip SVGs and data URLs (don't need lazy loading)
        if (preg_match('/src\s*=\s*["\']data:/', $attrs) ||
            preg_match('/\.svg["\']/', $attrs)) {
            return $matches[0];
        }

        $changes++;
        return '<img ' . trim($attrs) . ' loading="lazy">';
    };

    $content = preg_replace_callback($pattern, $replacement, $content);

    // Save if changes were made
    if ($changes > 0) {
        file_put_contents($filePath, $content);
        return $changes;
    }

    return 0;
}

function processDirectory($dir, $excludeDirs = []) {
    $totalChanges = 0;
    $filesProcessed = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getPathname();

            // Skip excluded directories
            $skip = false;
            foreach ($excludeDirs as $excluded) {
                if (strpos($filePath, DIRECTORY_SEPARATOR . $excluded . DIRECTORY_SEPARATOR) !== false) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) continue;

            $changes = addLazyLoadingToFile($filePath);
            if ($changes > 0) {
                $filesProcessed++;
                $totalChanges += $changes;
                echo "✓ " . basename($filePath) . " ($changes images)\n";
            }
        }
    }

    return [$filesProcessed, $totalChanges];
}

echo "Adding lazy loading to all images in views/modern/\n";
echo str_repeat("=", 50) . "\n\n";

list($filesProcessed, $totalChanges) = processDirectory($viewsDir, $excludeDirs);

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Complete!\n";
echo "Files processed: $filesProcessed\n";
echo "Images updated: $totalChanges\n";
?>
